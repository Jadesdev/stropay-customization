<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\BillPayCategory;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
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
use Http;

class BillPayController extends Controller
{
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index() {
        $page_title = __("Bill Pay");
        $billPayCharge = TransactionSetting::where('slug','bill_pay')->where('status',1)->first();
        $billType = BillPayCategory::active()->orderByDesc('id')->get();
        $transactions = Transaction::auth()->billPay()->latest()->take(10)->get();
        return view('user.sections.bill-pay.index',compact("page_title",'billPayCharge','transactions','billType'));
    }
    // BILLS
    public function bills_index() {
        $page_title = "Bill Pay";
        $dataplan = DataBundle::orderBy('network_id', 'asc')->whereStatus(1)->get();
        
        $transactions = Transaction::auth()->billPayment()->latest()->take(10)->get();
        $networks = Network::whereAirtime(1)->get();
        $cableplan = CablePlan::orderBy('decoder_id', 'asc')->whereStatus(1)->get();
        $decoders = Decoder::whereStatus(1)->get();
        $powers = Electricity::whereStatus(1)->get();
        return view('user.sections.bills.index',compact("page_title",'networks','transactions','dataplan','powers','cableplan','decoders'));
    }
    public function buyAirtime(Request $request){ 
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'phone' => 'required|digits:11|numeric',
            'network' => 'required|exists:networks,id',
            'pin' => 'required|digits:4',
        ]);
        $user = auth()->user();
        if($user->trx != $request->pin){
            return redirect()->back()->with(['error' => ['Incorrect Transaction PIN.']]);
        }
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
            }
        }
        $amount = $request->amount;
        $network = Network::findOrFail($request->network); 
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => ['Sender wallet not found']]);
        }
        if($amount > $userWallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        //Create trx
        try{
            $messg = "Pending Purchase of  {$network->name} Airtime worth ".($request->amount) .' for '.$request->phone;
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Airtime", $request->phone, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
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
            
            return back()->with(['success' => ['Airtime Purchased Successfully']]);
        }else{
            //cancel trx
            $trans->response = $response ?? "";
            $trans->status = 4;
            $trans->remark = "Purchase of {$network->name} Airtime worth  ".($request->amount) .' failed for '.$request->phone;
            $trans->save();
            
            $userWallet = UserWallet::where('user_id',$trans->user_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
            
            return back()->with(['error' => ['Sorry, Transaction was not successful.']]);
        }
        return $response;
        
    }
     //Buy data
    function buyData(Request $request){
        
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone' => 'required|digits:11|numeric',
            'network' => 'required|exists:networks,id',
            'plan' => 'required|exists:data_bundles,id',
            'pin' => 'required|digits:4',
        ]);
        $user = auth()->user();
        if($user->trx != $request->pin){
            return redirect()->back()->with(['error' => ['Incorrect Transaction PIN.']]);
        }
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
            }
        }
        $network = Network::findOrFail($request->network);
        $plan = DataBundle::findOrFail($request->plan);
        $amount = $plan->price;
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => ['Sender wallet not found']]);
        }
        if($amount > $userWallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        //Create trx
        try{
            $messg = 'Purchase of '.$plan->name.' to '. $request['phone'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Data", $request->phone, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
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
            
            return back()->with(['success' => [$mess]]);
        }else{
            //cancel trx
            $trans->status = 4;
            $trans->response = $response ?? "";
            $trans->remark = 'Purchase of '.$plan->name.' to '. $request['phone'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('user_id',$trans->user_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
            
            return back()->with(['error' => ['Purchase of '.$plan->name.' to '. $request['phone'].' was not successful']]);
        }
        return $request;
    }
    //Power
    function buyPower(Request $request){
         $request->validate([
            'amount' => 'required|numeric|min:1',
            'number' => 'required|numeric',
            'disco' => 'required|exists:electricities,id',
            'pin' => 'required|digits:4',
        ]);
        $user = auth()->user();
        if($user->trx != $request->pin){
            return redirect()->back()->with(['error' => ['Incorrect Transaction PIN.']]);
        }
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
            }
        }
        $disco = Electricity::findOrFail($request->disco);
        $amount = $request->amount;
        $min = $disco->minimum;
        $cost = $amount + $disco->fee;
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => ['Sender wallet not found']]);
        }
        if($cost > $userWallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        if($min > $amount ){
            return back()->with(['error' => ['Minimum amount is '. $min]]);
        }
        //create trx
        try{
            $messg = 'Purchase of '.$disco->name.' to '. $request['number'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$cost,"Electricity", $request->number, $messg);
            $this->insertSenderChargesB( $disco->fee,0, $disco->fee, $cost,$user,$trx);
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
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
            
            return back()->with(['success' => [$mess]]);
        }else{
            //cancel trx
            $trans->status = 4;
            $trans->response = $response ?? "";
            $trans->remark = "Purchase of {$amount} to ".$disco->name.' to '. $request['number'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('user_id',$trans->user_id)->first();
            $userWallet->balance +=  $cost;
            $userWallet->save();
            
            return back()->with(['error' => ["Purchase of {$amount} to ".$disco->name.' to '. $request['number'].' was not successful']]);
        }
        //return $request;
    }
    // Buy Cable
    function buyCable(Request $request){ 
        $request->validate([
            'plan' => 'required|numeric|exists:cable_plans,id',
            'decoder' => 'required|exists:decoders,id',
            'number' => 'required|min:9|numeric',
            'bypass' => 'string',
            'pin' => 'required|digits:4',
        ]);
        $user = auth()->user();
        if($user->trx != $request->pin){
            return redirect()->back()->with(['error' => ['Incorrect Transaction PIN.']]);
        }
        $basic_setting = BasicSettings::first();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
            }
        }
        $decoder = Decoder::find($request->decoder);
        $plan = CablePlan::findorFail($request->plan);
        $amount = $plan->price;
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => ['Sender wallet not found']]);
        }
        if($amount > $userWallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        //create trx
        try{
            $messg = 'Purchase of '.$plan->name.' to '. $request['number'];
            $trx_id = 'BP'.getTrxNum();
            $trx = $this->insertSenderB( $trx_id,$user,$userWallet,$amount,"Cable TV Subscription", $request->number, $messg);
            $this->insertSenderChargesB( 0,0, 0, $amount,$user,$trx);
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
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
            
            return back()->with(['success' => [$mess]]);
        }else{
            //cancel trx
            $trans->response = $response ?? "";
            $trans->status = 4;
            $trans->remark = "Purchase of ".$plan->name.' to '. $request['number'].' was not successful' ;
            $trans->save();
            
            $userWallet = UserWallet::where('user_id',$trans->user_id)->first();
            $userWallet->balance +=  $amount;
            $userWallet->save();
            
            return back()->with(['error' => ["Purchase of ".$plan->name.' to '. $request['number'].' was not successful']]);
        }
        return $request;
    }

    public function payConfirm(Request $request){
        $request->validate([
            'bill_type' => 'required|string',
            'bill_number' => 'required|min:8',
            'amount' => 'required|numeric|gt:0',

        ]);
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Please submit kyc information!')]]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Please wait before admin approved your kyc information')]]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Admin rejected your kyc information, Please re-submit again')]]);
            }
        }
        $amount = $request->amount;
        $billType = $request->bill_type;
        $bill_type = BillPayCategory::where('id', $billType)->first();
        $bill_number = $request->bill_number;
        $user = auth()->user();
        $billPayCharge = TransactionSetting::where('slug','bill_pay')->where('status',1)->first();
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => [__('User wallet not found!')]]);
        }
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default currency not found')]]);
        }

        $minLimit =  $billPayCharge->min_limit *  $rate;
        $maxLimit =  $billPayCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__("Please follow the transaction limit")]]);
        }
        //charge calculations
        $fixedCharge = $billPayCharge->fixed_charge *  $rate;
        $percent_charge = ($request->amount / 100) * $billPayCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $userWallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        try{
            $trx_id = 'BP'.getTrxNum();
            $sender = $this->insertSender( $trx_id,$user,$userWallet,$amount, $bill_type, $bill_number,$payable);
            $this->insertSenderCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$sender);
            if( $this->basic_settings->email_notification == true){
                $notifyData = [
                    'trx_id'  => $trx_id,
                    'bill_type'  => @$bill_type->name,
                    'bill_number'  => $bill_number,
                    'request_amount'   => $amount,
                    'charges'   => $total_charge,
                    'payable'  => $payable,
                    'current_balance'  => getAmount($userWallet->balance, 4),
                    'status'  => "Pending",
                ];
                //send notifications
                $user = auth()->user();
                $user->notify(new BillPayMail($user,(object)$notifyData));
            }
            return redirect()->route("user.bill.pay.index")->with(['success' => [__('Bill pay request send to admin successful')]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

    }
    public function insertSender( $trx_id,$user,$userWallet,$amount, $bill_type, $bill_number,$payable) {
        $trx_id = $trx_id;
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'bill_type_id' => $bill_type->id??'',
            'bill_type_name' => $bill_type->name??'',
            'bill_number' => $bill_number,
            'bill_amount' => $amount??"",
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::BILLPAY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::BILLPAY," ")) . " Request To Admin",
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
                'status'                        => 2,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function updateSenderWalletBalance($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertSenderCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id) {
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

            //notification
            $notification_content = [
                'title'         =>__("Bill Pay"),
                'message'       => __("Bill pay request send to admin successful")." " .$amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BILL_PAY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            event(new UserNotificationEvent($notification_content,$user));
            send_push_notification(["user-".$user->id],[
                'title'     => $notification_content['title'],
                'body'      => $notification_content['message'],
                'icon'      => $notification_content['image'],
            ]);

           //admin notification
           $notification_content['title'] = __("Bill pay request send to admin successful ")." ".$amount.' '.get_default_currency_code().' '.__("Successful").' ('.$user->username.')';
           AdminNotification::create([
               'type'      => NotificationConst::BILL_PAY,
               'admin_id'  => 1,
               'message'   => $notification_content,
           ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
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
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
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
