<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Events\User\NotificationEvent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\Currency;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\TemporaryData;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use App\Models\UserWallet;
use Exception;
use Illuminate\Support\Facades\Session;
use App\Traits\PaymentGateway\Stripe;
use App\Traits\PaymentGateway\Manual;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\CryptoTransaction;
use App\Models\Merchants\Merchant;
use App\Models\User;
use App\Models\UserNotification;
use App\Traits\PaymentGateway\FlutterwaveTrait;
use App\Traits\PaymentGateway\SslcommerzTrait;
use App\Traits\PaymentGateway\RazorTrait;
use App\Traits\PaymentGateway\MonnifyTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\User\NotificationEvent as UserNotificationEvent;
use App\Models\Merchants\MerchantNotification;
use Jenssegers\Agent\Agent;
use Str;
use KingFlamez\Rave\Facades\Rave as Flutterwave;

class AddMoneyController extends Controller
{
    use Stripe,Manual,FlutterwaveTrait,RazorTrait,SslcommerzTrait, MonnifyTrait;
    public function index() {
        $page_title = __("Add Money");
        $user_wallets = UserWallet::auth()->get();
        $user_currencies = Currency::whereIn('id',$user_wallets->pluck('id')->toArray())->get();

        $payment_gateways_currencies = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::add_money_slug());
            $gateway->where('status', 1);
        })->get();
        $transactions = Transaction::auth()->addMoney()->latest()->take(10)->get();
        $banks = auth()->user()->monnify_banks;
        $banks = \json_decode($banks);
        return view('user.sections.add-money.index',compact("page_title","transactions","payment_gateways_currencies","banks"));
    }

    public function submit(Request $request) {
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if($basic_setting->kyc_verification){
                if( $user->kyc_verified == 0){
                    return redirect()->route('user.profile.index')->with(['error' => [__('Please submit kyc information!')]]);
                }elseif($user->kyc_verified == 2){
                    return redirect()->route('user.profile.index')->with(['error' => [__('Please wait before admin approved your kyc information')]]);
                }elseif($user->kyc_verified == 3){
                    return redirect()->route('user.profile.index')->with(['error' => [__('Admin rejected your kyc information, Please re-submit again')]]);
                }
            }
        }
        try{
            $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEADDMONEY)->gateway()->render();
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return $instance;
    }

    public function success(Request $request, $gateway){
        $requestData = $request->all();
        $token = $requestData['token'] ?? "";
        $checkTempData = TemporaryData::where("type",$gateway)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();

        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive();
        }catch(Exception $e) {

            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__("Successfully Added Money")]]);
    }

    public function cancel(Request $request, $gateway) {
        $token = session()->get('identifier');
        if( $token){
            TemporaryData::where("identifier",$token)->delete();
        }
        return redirect()->route('user.add.money.index');
    }
    
    //Generate Bank accounts
    public function virtualAccounts(){
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
            }
        }
        $reference = $user['username'].Str::random(6);
        $gateway = PaymentGateway::where('type',"AUTOMATIC")->where('alias','monnify')->first();
        $d['gateway'] = $gateway;
        $credentials = $this->getMonnifyCredentials($d);
        //check if user has bank accounts
        if($user->monnify_ref == null){
            $formdata = [
                'customerEmail' => $user['email'],
                'customerName' => $user['firstname'],
                'accountName' => $user['username'],
                'accountReference' => $reference,
                'currencyCode' => "NGN",
                // "bvn" => "",
                "contractCode" => $credentials->contract_code,
                "getAllAvailableBanks" => true,
                //"preferredBanks" => ["035","232","058"]
            ];
            $accounts = $this->createMonnifyAccounts($formdata, $credentials);
            if($accounts['responseMessage'] == 'success'){
                $banks = $accounts['responseBody']['accounts'];
                $user->monnify_ref = $reference;
                $user->monnify_banks = $banks;
                $user->save();
                return back()->with(['success' => ['Bank Account Numbers generated successfully.']]);
            }else{
                return back()->with(['error' => ['Unable to generate accounts. Please try again.']]);
            }
            
        }else{
            return back()->with(['error' =>['You already have Account Numbers generated.']]);
        }
    }

    public function manualPayment(){
        $tempData = Session::get('identifier');
        $hasData = TemporaryData::where('identifier', $tempData)->first();
        $gateway = PaymentGateway::manual()->where('slug',PaymentGatewayConst::add_money_slug())->where('id',$hasData->data->gateway)->first();
        $page_title = __("Manual Payment")." ".' ( '.$gateway->name.' )';
        if(!$hasData){
            return redirect()->route('user.add.money.index');
        }
        return view('user.sections.add-money.manual.payment_confirmation',compact("page_title","hasData",'gateway'));
    }

    public function flutterwaveCallback()
    {
        $status = request()->status;
        //if payment is successful
        if ($status ==  'successful' || $status == 'completed') {
            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data = Flutterwave::verifyTransaction($transactionID);

            $requestData = request()->tx_ref;
            $token = $requestData;

            $checkTempData = TemporaryData::where("type",'flutterwave')->where("identifier",$token)->first();

            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again')]]);

            $checkTempData = $checkTempData->toArray();

            try{
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('flutterWave');
            }catch(Exception $e) {
                return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__("Successfully Added Money")]]);

        }
        elseif ($status ==  'cancelled'){
            return redirect()->route('user.add.money.index')->with(['error' => [__('Add money cancelled')]]);
        }
        else{
            return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction failed")]]);
        }
    }
    //Monnify Success
    public function monnifySuccess(Request $request)
    {
        $request_data = request()->all();
        $tempData = $request['paymentReference'] ?? Session::get('identifier');
        //verify payment status
        $data = [
			'paymentReference' => $tempData
		] ;
        $status = $this->verifyMonnifyTrx($data);
        //if payment is successful
        if ($status == 'PAID') {

            $checkTempData = TemporaryData::where("type",'monnify')->where("identifier",$tempData)->first();

            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => ['Transaction Failed. Record didn\'t saved properly. Please try again.']]);

            $checkTempData = $checkTempData->toArray();

            try{
               PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('monnify');
            }catch(Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => ['Successfully added money']]);

        }
        elseif ($status ==  'cancelled'){
            return redirect()->route('user.add.money.index')->with(['error' => ['Add money cancelled']]);
        }
        else{
            return redirect()->route('user.add.money.index')->with(['error' => ['Transaction failed']]);
        }
    }
    public function razorPayment($trx_id){
        $identifier = $trx_id;
        $output = TemporaryData::where('identifier', $identifier)->first();
        if(!$output){
            return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction failed")]]);
        }
        $data =  $output->data->response;
        $orderId =  $output->data->response->order_id;
        $page_title = __('razor Pay Payment');

        return view('user.sections.add-money.automatic.razor', compact('page_title','output','data','orderId'));
    }
    public function razorCallback()
    {
        $request_data = request()->all();
        //if payment is successful
        if (isset($request_data['razorpay_order_id'])) {
            $token = $request_data['razorpay_order_id'];

            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::RAZORPAY)->where("identifier",$token)->first();
            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
            $checkTempData = $checkTempData->toArray();
            try{
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('razorpay');
            }catch(Exception $e) {
                return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__("Successfully Added Money")]]);

        }
        else{
            return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction failed")]]);
        }
    }
    public function razorCancel($trx_id){
        $token = $trx_id;
        if( $token){
            TemporaryData::where("identifier",$token)->delete();
        }
        return redirect()->route("user.add.money.index")->with(['error' => [__('Add money cancelled')]]);
    }

    //stripe success
    public function stripePaymentSuccess($trx){
        $token = $trx;
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::STRIPE)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
        $checkTempData = $checkTempData->toArray();
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('stripe');
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    //sslcommerz success
    public function sllCommerzSuccess(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;

        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] != "VALID"){
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Failed')]]);
        }
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('sslcommerz');
        }catch(Exception $e) {
            return back()->with(['error' => ["Something Is Wrong..."]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => ['Successfully Added Money']]);
    }
    //sslCommerz fails
    public function sllCommerzFails(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] == "FAILED"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Failed')]]);
        }

    }
    //sslCommerz canceled
    public function sllCommerzCancel(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] != "VALID"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Add money cancelled')]]);
        }
    }
    //coingate response start
    public function coinGateSuccess(Request $request, $gateway){

        try{
            $token = $request->token;
            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::COINGATE)->where("identifier",$token)->first();
            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            }else {
                if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }
            $update_temp_data = json_decode(json_encode($checkTempData->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('coingate');
        }catch(Exception $e) {
            return redirect()->route("user.add.money.index")->with(['error' => [__('Something went wrong! Please try again.')]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function coinGateCancel(Request $request, $gateway){
        if($request->has('token')) {
            $identifier = $request->token;
            if($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }
        return redirect()->route("user.add.money.index")->with(['error' => [__('Add money cancelled')]]);
    }
    public function callback(Request $request,$gateway){
        $callback_token = $request->get('token');
        $callback_data = $request->all();
        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }
    //coingate response end

    public function cryptoPaymentAddress(Request $request, $trx_id) {
        $page_title =__( "Crypto Payment Address");
        $transaction = Transaction::where('trx_id', $trx_id)->firstOrFail();
        if($transaction->gateway_currency->gateway->isCrypto() && $transaction->details?->payment_info?->receiver_address ?? false) {
            return view('user.sections.add-money.payment.crypto.address', compact(
                'transaction',
                'page_title',
            ));
        }

        return abort(404);
    }

    public function cryptoPaymentConfirm(Request $request, $trx_id)
    {
        $transaction = Transaction::where('trx_id',$trx_id)->where('status', PaymentGatewayConst::STATUSWAITING)->firstOrFail();
        $user =  $transaction->user;
        $gateway_currency =  $transaction->currency->alias;

        $request_data = $request->merge([
            'currency' => $gateway_currency,
            'amount' => $transaction->request_amount,
        ]);
        $output = PaymentGatewayHelper::init($request_data->all())->type(PaymentGatewayConst::TYPEADDMONEY)->gateway()->get();

        $dy_input_fields = $transaction->details->payment_info->requirements ?? [];
        $validation_rules = $this->generateValidationRules($dy_input_fields);

        $validated = [];
        if(count($validation_rules) > 0) {
            $validated = Validator::make($request->all(), $validation_rules)->validate();
        }

        if(!isset($validated['txn_hash'])) return back()->with(['error' => [__('Transaction hash is required for verify')]]);

        $receiver_address = $transaction->details->payment_info->receiver_address ?? "";

        // check hash is valid or not
        $crypto_transaction = CryptoTransaction::where('txn_hash', $validated['txn_hash'])
                                                ->where('receiver_address', $receiver_address)
                                                ->where('asset',$transaction->gateway_currency->currency_code)
                                                ->where(function($query) {
                                                    return $query->where('transaction_type',"Native")
                                                                ->orWhere('transaction_type', "native");
                                                })
                                                ->where('status',PaymentGatewayConst::NOT_USED)
                                                ->first();

        if(!$crypto_transaction) return back()->with(['error' => [__('Transaction hash is not valid! Please input a valid hash')]]);

        if($crypto_transaction->amount >= $transaction->total_payable == false) {
            if(!$crypto_transaction) return back()->with(['error' => [__("Insufficient amount added. Please contact with system administrator")]]);
        }

        DB::beginTransaction();
        try{

            // Update user wallet balance
            DB::table($transaction->creator_wallet->getTable())
                ->where('id',$transaction->creator_wallet->id)
                ->increment('balance',$transaction->request_amount);

            // update crypto transaction as used
            DB::table($crypto_transaction->getTable())->where('id', $crypto_transaction->id)->update([
                'status'        => PaymentGatewayConst::USED,
            ]);

            // update transaction status
            $transaction_details = json_decode(json_encode($transaction->details), true);
            $transaction_details['payment_info']['txn_hash'] = $validated['txn_hash'];

            DB::table($transaction->getTable())->where('id', $transaction->id)->update([
                'details'       => json_encode($transaction_details),
                'status'        => PaymentGatewayConst::STATUSSUCCESS,
                'available_balance'        => $transaction->available_balance + $transaction->request_amount,
            ]);

             //notification
             $notification_content = [
                'title'         => __("Add Money"),
                'message'       => __("Your Wallet")." (".$output['wallet']->currency->code.")  ".__("balance  has been added")." ".$output['amount']->requested_amount.' '. $output['wallet']->currency->code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  $user->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            event(new NotificationEvent($notification_content,$user));
            send_push_notification(["user-".$user->id],[
                'title'     => $notification_content['title'],
                'body'      => $notification_content['message'],
                'icon'      => $notification_content['image'],
            ]);
            //admin notification
            $notification_content['title'] = __("Add Money ").' '.$output['amount']->requested_amount.' '.$output['amount']->default_currency.'  '.__('By').' '. $output['currency']->name.' ('.$user->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);

            DB::commit();

        }catch(Exception $e) {
            DB::rollback();
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return back()->with(['success' => [__('Payment Confirmation Success')]]);
    }

    public function monnifyWebhookNotification(Request $request){
        $input = $request->all();
        $data = [
			'paymentReference' => $input['eventData']['paymentReference']
		];
		$gateway = PaymentGateway::where('type',"AUTOMATIC")->where('alias','monnify')->first();
        $d['gateway'] = $gateway;
        $credentials = $this->getMonnifyCredentials($d);
        $response = $this->verifyMonnifyWebhook($data, $credentials);
        if($response['responseMessage'] == 'success' && $response['responseBody']['paymentStatus'] == "PAID"){
            if($input['eventData']['paymentMethod'] == "ACCOUNT_TRANSFER"){
                $details['amount'] = $input['eventData']['amountPaid'];
                $details['reference'] = $input['eventData']['product']['reference'];
                $details['final'] = $input['eventData']['settlementAmount'];
                
                return $this->complete_monnifyWebhok($details, $response = null);
                
                return ['status' => 'success'];
            }
        }else{
            return ['status' => 'error'];
        }
        return $request->all();
    }
    
    function complete_monnifyWebhok($details, $response = null){
        
        $user = User::where('monnify_ref', $details['reference'])->first();
        if($user != null){
            return $this->complete_userMonnifyWebhok($user, $details, $response = null);
        }
        //try merchant
        $merchant = Merchant::where('monnify_ref', $details['reference'])->first();
        if($merchant != null){
            return $this->complete_merchantMonnifyWebhok($merchant, $details, $response = null);
        }
        return ['error' => 'No user found'];
    }

    function complete_userMonnifyWebhok($user, $details, $response = null){
        
        $userWallet = $user->wallet;
        // add user balance and create trx
        $fee = '1.6';
        $charge = ($fee * $details['amount'])/100;
        $amount = $details['amount'] - $charge;
        DB::beginTransaction();
        try{
            $trx_id = 'AM'.getTrxNum();
            // Add money
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $userWallet->id,
                'payment_gateway_currency_id'   => '34',
                'type'                          => "ADD-MONEY",
                'trx_id'                        => $trx_id,
                'request_amount'                => $details['amount'],
                'payable'                       => $amount,
                'available_balance'             => $userWallet->balance + $amount,
                'remark'                        => "Bank Account Deposits Successful.",
                'details'                       => 'Bank Account Deposits',
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            
            $update_amount = $userWallet->balance + $amount;

            $userWallet->update([
                'balance'   => $update_amount,
            ]);

            DB::commit();
            
        }catch(Exception $e) {
            DB::rollBack();
            //return ['status' => 'error'];
            throw new Exception($e->getMessage());
        }
        // Add Charges
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    =>1.5,
                'fixed_charge'      => 0,
                'total_charge'      => $charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Add Money",
                'message'       => "Your Wallet (".$userWallet->currency->code.") balance  has been added ".$amount.' '. $userWallet->currency->code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  $user->id,
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
             $notification_content['title'] = 'Add Money '.$amount.' NGN '.' By  ('.$user->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        //Add Device Info
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        // $mac = exec('getmac');
        // $mac = explode(" ",$mac);
        // $mac = array_shift($mac);
        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }

        return ['status' => 'success'];
    }
    
    function complete_merchantMonnifyWebhok($merchant, $details, $response = null){
        
        $userWallet = $merchant->wallet;
        // add user balance and create trx
        $fee = '1.6';
        $charge = ($fee * $details['amount'])/100;
        $amount = $details['amount'] - $charge;
        DB::beginTransaction();
        try{
            $trx_id = 'AM'.getTrxNum();
            // Add money
            $id = DB::table("transactions")->insertGetId([
                'merchant_id'                       => $merchant->id,
                'merchant_wallet_id'                => $userWallet->id,
                'payment_gateway_currency_id'   => '34',
                'type'                          => "ADD-MONEY",
                'trx_id'                        => $trx_id,
                'request_amount'                => $details['amount'],
                'payable'                       => $amount,
                'available_balance'             => $userWallet->balance + $amount,
                'remark'                        => "Bank Account Deposits Successful.",
                'details'                       => 'Bank Account Deposits',
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            
            $update_amount = $userWallet->balance + $amount;

            $userWallet->update([
                'balance'   => $update_amount,
            ]);

            DB::commit();
            
        }catch(Exception $e) {
            DB::rollBack();
            //return ['status' => 'error'];
            throw new Exception($e->getMessage());
        }
        // Add Charges
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    =>1.5,
                'fixed_charge'      => 0,
                'total_charge'      => $charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Add Money",
                'message'       => "Your Wallet (".$userWallet->currency->code.") balance  has been added ".$amount.' '. $userWallet->currency->code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => get_image($merchant->image,'user-profile'),
            ];

            MerchantNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'merchant_id'  => $userWallet->merchant->id,
                'message'   => $notification_content,
            ]);
            event(new NotificationEvent($notification_content,$merchant));
           
            send_push_notification(["merchant-".$merchant->id],[
                 'title'     => $notification_content['title'],
                 'body'      => $notification_content['message'],
                 'icon'      => $notification_content['image'],
             ]);

            //admin notification
            $notification_content['title'] = 'Add Money '.$amount.' NGN '.' By  ('.$merchant->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        //Add Device Info
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        // $mac = exec('getmac');
        // $mac = explode(" ",$mac);
        // $mac = array_shift($mac);
        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }

        return ['status' => 'success'];
    }
}
