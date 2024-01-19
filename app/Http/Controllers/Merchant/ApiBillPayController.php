<?php

namespace App\Http\Controllers\Merchant;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\BillPayCategory;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\Merchants\MerchantWallet as UserWallet;
use App\Notifications\User\BillPay\BillPayMail;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\User\NotificationEvent as UserNotificationEvent;
use App\Models\Admin\AdminNotification;
use App\Models\{
    DataBundle, Network, Electricity, Decoder, CablePlan
};
use App\Models\Merchants\PaymentOrderRequest;
use App\Http\Helpers\Response;
use Carbon\Carbon;
use Http;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\Validator;

class ApiBillPayController extends Controller
{
    protected $basic_settings;
    protected $access_token_expire_time = 60000;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function checkApiCred($request){
        $access_token = $request->bearerToken();
        if(!$access_token) return Response::paymentApiError([__('Access denied! Token not found')],[],403);

        $request_record = PaymentOrderRequest::where('access_token',$access_token)->first();
        if(!$request_record) return Response::paymentApiError([__('Requested with invalid token!')],[],403);

        if(Carbon::now() > $request_record->created_at->addSeconds($this->access_token_expire_time)) {
            try{
                $request_record->update([
                    'status'    => PaymentGatewayConst::EXPIRED,
                ]);
            }catch(Exception $e) {
                return Response::paymentApiError([__("Failed to create payment! Please try again")],[],500);
            }
        }

        if($request_record->status == PaymentGatewayConst::EXPIRED) return Response::paymentApiError([__('Request token is expired')],[],401);

        if($request_record->status != PaymentGatewayConst::CREATED) return Response::paymentApiError([__('Requested with invalid token!')],[],400);

        $merchant = $request_record->merchant;
        auth()->login($merchant);
    }
    
    public function buyAirtime(Request $request){ 
        $checkapi =  $this->checkApiCred($request);
        if($checkapi){
            return $checkapi;
        }
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'phone' => 'required|digits:11|numeric',
            'network' => 'required|exists:networks,id',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $user = auth()->user();
        
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                $error = (['error' => ['Please submit kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 2){
                $error = (['error' => ['Please wait before admin approved your kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 3){
                $error = (['error' => ['Admin rejected your kyc information, Please re-submit again']]);
                return Helpers::error($error);
            }
        }
        $amount = $request->amount;
        $network = Network::findOrFail($request->network); 
        $userWallet = UserWallet::where('merchant_id',$user->id)->first();
        if(!$userWallet){
            $error = (['error' => ['Sender wallet not found']]);
            return Helpers::error($error);
        }
        if($amount > $userWallet->balance ){
            $error = (['error' => ['Sorry, insufficient balance']]);
            return Helpers::error($error);
        }
        //Create trx
        try{
            $messg = "Pending Purchase of  {$network->name} Airtime worth ".($request->amount) .' for '.$request->phone;
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Airtime", $request->phone, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            $error = (['error' => [$e->getMessage()]]);
            return Helpers::error($error);
        }
        //Send request to API
        $payload = [
            'network' => $network->code,
            'mobile_number' => $request['phone'],
            'airtime_type' => "VTU",
            'Ported_number' => true,
            'amount' => $request['amount'],
        ];
        $trans = Transaction::find($trx);
        
        $response = $this->sendAirtimeApi($payload);
        if(isset($response['Status']) && $response['Status']== "successful"){
            //complete trx
            $trans->response = $response ?? "";
            $trans->status = 1;
            $trans->remark = "You Successfully Purchased {$network->name} Airtime worth  ".($request->amount) .' for '.$request->phone;
            $trans->save();
            
            return  Response::success("Airtime Purchased Successfully", $trans);
        }else{
            //cancel trx
            $trans->response = $response ?? "";
            $trans->status = 4;
            $trans->remark = "Purchase of {$network->name} Airtime worth  ".($request->amount) .' failed for '.$request->phone;
            $trans->save();
            
            $userWallet = UserWallet::where('merchant_id',$trans->merchant_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
            
            $error = (['error' => ['Sorry, Transaction was not successful.']]);
            return Response::error("Transaction was not successful");
        }
        return $response;
        
    }
     //Buy data
    function buyData(Request $request){
        $checkapi =  $this->checkApiCred($request);
        if($checkapi){
            return $checkapi;
        }
        $validator = Validator::make($request->all(), [
            'phone' => 'required|digits:11|numeric',
            'network' => 'required|exists:networks,id',
            'plan' => 'required|exists:data_bundles,id',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $user = auth()->user();
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                $error = (['error' => ['Please submit kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 2){
                $error = (['error' => ['Please wait before admin approved your kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 3){
                $error = (['error' => ['Admin rejected your kyc information, Please re-submit again']]);
                return Helpers::error($error);
            }
        }
        $network = Network::findOrFail($request->network);
        $plan = DataBundle::findOrFail($request->plan);
        $amount = $plan->price;
        $userWallet = UserWallet::where('merchant_id',$user->id)->first();
        if(!$userWallet){
            $error = (['error' => ['Sender wallet not found']]);
            return Helpers::error($error);
        }
        if($amount > $userWallet->balance ){
            $error = (['error' => ['Sorry, insufficient balance']]);
            return Helpers::error($error);
        }
        //Create trx
        try{
            $messg = 'Purchase of '.$plan->name.' to '. $request['phone'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Data", $request->phone, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            $error = (['error' => [$e->getMessage()]]);
            return Helpers::error($error);
        }
        //Send to api
        $trans = Transaction::find($trx);
        $payload = [
            'network' => ($network->code),
            'mobile_number' => $request->phone,
            'Ported_number' => true,
            'plan' => $plan->code,
        ];
        $response = $this->sendDataApi($payload);
        if(isset($response['Status']) && $response['Status']== "successful"){
            $mess = $response['api_response'] ?? 'Purchase of '.$plan->name.' to '. $request['phone'].' was successful';
            //complete trx
            $trans->status = 1;
            $trans->response = $response ?? "";
            $trans->remark = $mess;
            $trans->save();
            
            return  Response::success($mess, $trans);
        }else{
            //cancel trx
            $trans->status = 4;
            $trans->response = $response ?? "";
            $trans->remark = 'Purchase of '.$plan->name.' to '. $request['phone'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('merchant_id',$trans->merchant_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
           
            return Response::error($trans->remark);
        }
        return $request;
    }
    //Power
    function buyPower(Request $request){
        $checkapi =  $this->checkApiCred($request);
        if($checkapi){
            return $checkapi;
        }
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'number' => 'required|numeric',
            'disco' => 'required|exists:electricities,id',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                $error = (['error' => ['Please submit kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 2){
                $error = (['error' => ['Please wait before admin approved your kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 3){
                $error = (['error' => ['Admin rejected your kyc information, Please re-submit again']]);
                return Helpers::error($error);
            }
        }
        $disco = Electricity::findOrFail($request->disco);
        $amount = $request->amount;
        $min = $disco->minimum;
        $cost = $amount + $disco->fee;
        $userWallet = UserWallet::where('merchant_id',$user->id)->first();
        if(!$userWallet){
            $error = (['error' => ['Sender wallet not found']]);
            return Helpers::error($error);
        }
        if($cost > $userWallet->balance ){
            $error = (['error' => ['Sorry, insufficient balance']]);
            return Helpers::error($error);
        }
        if($min > $amount ){
            $error = (['error' => ['Minimum amount is '. $min]]);
            return Helpers::error($error);
        }
        //create trx
        try{
            $messg = 'Purchase of '.$disco->name.' to '. $request['number'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$cost,"Electricity", $request->number, $messg);
            $this->insertSenderChargesB( $disco->fee,0, $disco->fee, $cost,$user,$trx);
        }catch(Exception $e) {
            $error = (['error' => [$e->getMessage()]]);
            return Helpers::error($error);
        }
        //Send to api
        $trans = Transaction::find($trx);
        
        $payload = [
            'meter_number' => $request['number'],
            'disco_name' => $disco->code,
            'Customer_Phone' => $user->mobile,
            'customer_name' => $user['firstname'],
            'customer_address' => " ",
            'amount' => $amount,
            'MeterType' => $request['type'],
        ];
        $response = $this->sendPowerApi($payload);
        if(isset($response['Status']) && $response['Status']== "successful"){
            $tok = $response['token'] ?? "";
            $mess = "Purchase of {$amount} to ".$disco->name.' to '. $request['number'].' Token : '.$tok;
            //complete trx
            $trans->status = 1;
            $trans->remark = $mess;
            $trans->response = $response ?? "";
            $trans->save();
            
            return  Response::success($mess, $trans);
        }else{
            //cancel trx
            $trans->status = 4;
            $trans->response = $response ?? "";
            $trans->remark = "Purchase of {$amount} to ".$disco->name.' to '. $request['number'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('merchant_id',$trans->merchant_id)->first();
            $userWallet->balance +=  $cost;
            $userWallet->save();
                       
            return Response::error($trans->remark);
        }
        //return $request;
    }
    // Buy Cable
    function buyCable(Request $request){ 
        $checkapi =  $this->checkApiCred($request);
        if($checkapi){
            return $checkapi;
        }
        $validator = Validator::make($request->all(), [
            'plan' => 'required|numeric|exists:cable_plans,id',
            'decoder' => 'required|exists:decoders,id',
            'number' => 'required|min:9|numeric',
            'bypass' => 'string',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $user = auth()->user();
        
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                $error = (['error' => ['Please submit kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 2){
                $error = (['error' => ['Please wait before admin approved your kyc information']]);
                return Helpers::error($error);
            }elseif($user->kyc_verified == 3){
                $error = (['error' => ['Admin rejected your kyc information, Please re-submit again']]);
                return Helpers::error($error);
            }
        }
        $decoder = Decoder::find($request->decoder);
        $plan = CablePlan::findorFail($request->plan);
        $decoder = $plan->decoder;
        $amount = $plan->price;
        $userWallet = UserWallet::where('merchant_id',$user->id)->first();
        if(!$userWallet){
            $error = (['error' => ['Sender wallet not found']]);
            return Helpers::error($error);
        }
        if($amount > $userWallet->balance ){
            $error = (['error' => ['Sorry, insufficient balance']]);
            return Helpers::error($error);
        }
        //create trx
        try{
            $messg = 'Purchase of '.$plan->name.' to '. $request['number'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Cable TV Subscription", $request->number, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            $error = (['error' => [$e->getMessage()]]);
            return Helpers::error($error);
        }
        //Send to api
        $trans = Transaction::find($trx);
        $payload = [
            'cablename' => ($decoder->id),
            'smart_card_number' => $request['number'],
            'cableplan' => $plan->code,
            'customer_name' => $user->firstname
        ];
        $response = $this->sendCableApi($payload);
        if(isset($response['Status']) && $response['Status']== "successful"){
            $mess = "Purchase of ".$plan->name.' to '. $request['number'].' was successful';
            //complete trx
            $trans->status = 1;
            $trans->remark = $mess;
            $trans->response = $response ?? "";
            $trans->save();
            
            return Response::success($mess, $trans);
            return back()->with(['success' => [$mess]]);
        }else{
            //cancel trx
            $trans->response = $response ?? "";
            $trans->status = 4;
            $trans->remark = "Purchase of ".$plan->name.' to '. $request['number'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('merchant_id',$trans->merchant_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
            
            return Response::error($trans->remark);
            return back()->with(['error' => ["Purchase of ".$plan->name.' to '. $request['number'].' was not successful']]);
        }
        return $request;
    }

    function networks(Request $request){
        $networks = Network::whereStatus(1)->get();
        $res  = [];
        foreach($networks as $item){
            $res[] = [
                'id' => $item['id'],
                'name' => $item['name'],
            ];

        }
        return Response::success("Network fetched successfully ",$res);
    }
    // Get data plans
    function data_plans(Request $request)
    {
        $option_plan = [];
        if($request->network){
            $dataplan = DataBundle::where('network_id', $request->network)->whereStatus(1)->get();
        }else{
            $dataplan = DataBundle::whereStatus(1)->get();
        }

        if ($dataplan->count() > 0) {
            foreach ($dataplan as $fetch_data) {
                $option_plan[] = [
                    'network' => $fetch_data['network_id'],
                    'id' => $fetch_data['id'],
                    'type' => $fetch_data['service'],
                    'price' => $fetch_data['price'],
                    'name' => $fetch_data['name'] . ' - ₦' . $fetch_data['price'],
                ];
            }

            return Response::success('Data plans fetched successfully',$option_plan);
        }
        
        return Response::error("No dataplan found");
    }
    // Cables
    function cables(){
        $cables = Decoder::whereStatus(1)->get();
        $res  = null;
        foreach($cables as $item){
            $res[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'status' => $item['status'],
            ];

        }

        return Response::success("Decoders fetched successfully ",$res);
    }
    function cable_plans(Request $request)
    {
        $option_plan = [];
        if($request->cable){
            $cableplan = CablePlan::where('decoder_id', $request->cable)->whereStatus(1)->get();
        }else{
            $cableplan = CablePlan::whereStatus(1)->get();
        }
        if ($cableplan->count() > 0) {
            foreach ($cableplan as $fetch_data) {
                $option_plan[] = [
                    'cable' => $fetch_data['decoder_id'],
                    'id' => $fetch_data['id'],
                    'price' => $fetch_data['price'],
                    'name' => $fetch_data['name'] . ' - ₦' . $fetch_data['price'],
                ];
            }

            return Response::success('Decoder plans fetched successfully',$option_plan);
        }

        return Response::error("No Cable Plan found");
    }
    // ELectricity Payment
    function powers(){
        $plans = Electricity::whereStatus(1)->get();
        $res  = [];
        foreach($plans as $item){
            $res[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'fee' => $item['fee'],
                'minimum' => $item['minimum'],
                'status' => $item['status'],
            ];

        }

        return Response::success("Power Plans fetched successfully ",$res);
    }


    //Bills
    public function insertSenderB( $trx_id,$user,$userWallet,$amount, $bill_type, $bill_number, $messg) {
        $trx_id = $trx_id;
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $amount);
        $details =[
            'bill_type' => $bill_type,
            'bill_number' => $bill_number,
            'bill_amount' => $amount??"",
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'merchant_id'                       => $user->id,
                'merchant_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::BILLPAYMENT,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $amount,
                'available_balance'             => $afterCharge,
                'remark'                        => $messg,
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
                'status'                        => 2,
                'created_at'                    => now(),
            ]);
            
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function insertSenderChargesB($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    public function updateSenderWalletBalance($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }
    
    public function sendAirtimeApi($data){
        $response = Http::timeout(120)->withHeaders([
            'Authorization' => 'Token '.env('GLAD_API')
        ])->post('https://www.gladtidingsdata.com/api/topup/', $data)->json();

        return $response;
    }
    public function sendDataApi($data){
        $response = Http::timeout(120)->withHeaders([
            'Authorization' => 'Token '.env('GLAD_API')
        ])->post('https://www.gladtidingsdata.com/api/data/', $data)->json();

        return $response;
    }
    public function sendCableApi($data){
        $response = Http::timeout(120)->withHeaders([
            'Authorization' => 'Token '.env('GLAD_API')
        ])->post('https://www.gladtidingsdata.com/api/cablesub/', $data)->json();

        return $response;
    }
    public function sendPowerApi($data){
        $response = Http::timeout(120)->withHeaders([
            'Authorization' => 'Token '.env('GLAD_API')
        ])->post('https://www.gladtidingsdata.com/api/billpayment/', $data)->json();

        return $response;
    }
}
