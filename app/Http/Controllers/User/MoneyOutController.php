<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserWallet;
use App\Models\Admin\Currency;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\Transaction;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Validator;
use App\Traits\ControlDynamicInputFields;
use Exception;
use App\Events\User\NotificationEvent as UserNotificationEvent;
use App\Models\Admin\AdminNotification;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\Admin\BasicSettings;
use App\Models\Merchants\MerchantWallet;
use App\Notifications\User\Withdraw\WithdrawMail;
use App\Traits\PaymentGateway\FlutterwaveTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MoneyOutController extends Controller
{
    use ControlDynamicInputFields, FlutterwaveTrait;
    public function index()
    {
        $page_title = __("Withdraw Money");
        $user_wallets = UserWallet::auth()->get();
        $user_currencies = Currency::whereIn('id',$user_wallets->pluck('id')->toArray())->get();
        $payment_gateways = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->get();
        $transactions = Transaction::auth()->moneyOut()->orderByDesc("id")->latest()->take(10)->get();
        $allBanks = getFlutterwaveBanks('NG');
        return view('user.sections.money-out.index',compact('page_title','payment_gateways','transactions','user_wallets','allBanks'));
    }
    
    public function placeTransfer(Request $request){
        $request->validate([
           'amount' => 'required|numeric|gt:0',
           'gateway' => 'required',
           'pin' => 'required|digits:4',
           'bank_name' => 'required|numeric|gt:0',
           'account_number' => 'required',
           'narration' => 'required|string|min:5'
       ]);
       $basic_setting = BasicSettings::first();
       $user = auth()->user();
       if($user->trx != $request->pin){
            return redirect()->back()->with(['error' => ['Incorrect Transaction PIN.']]);
       }
       if($basic_setting->kyc_verification){
           if( $user->kyc_verified == 0){
               return redirect()->route('user.profile.index')->with(['error' => ['Please submit kyc information']]);
           }elseif($user->kyc_verified == 2){
               return redirect()->route('user.profile.index')->with(['error' => ['Please wait before admin approved your kyc information']]);
           }elseif($user->kyc_verified == 3){
               return redirect()->route('user.profile.index')->with(['error' => ['Admin rejected your kyc information, Please re-submit again']]);
           }
       }
       
       $userWallet = UserWallet::where('user_id',$user->id)->where('status',1)->first();
       $gate =PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
           $gateway->where('slug', PaymentGatewayConst::money_out_slug());
           $gateway->where('status', 1);
       })->where('alias',$request->gateway)->first();
       $baseCurrency = Currency::default();
       if (!$gate) {
           return back()->with(['error' => ['Invalid Gateway']]);
       }
       $amount = $request->amount;

       $min_limit =  $gate->min_limit / $gate->rate;
       $max_limit =  $gate->max_limit / $gate->rate;
       if($amount < $min_limit || $amount > $max_limit) {
           return back()->with(['error' => ['Please follow the transaction limit']]);
       }
       //gateway charge
       $fixedCharge = $gate->fixed_charge;
       $percent_charge =  (((($request->amount * $gate->rate)/ 100) * $gate->percent_charge));
       $charge = $fixedCharge + $percent_charge;
       $conversion_amount = $request->amount * $gate->rate;
       $will_get = $conversion_amount -  $charge;

       //base_cur_charge
       $baseFixedCharge = $gate->fixed_charge *  $baseCurrency->rate;
       $basePercent_charge = ($request->amount / 100) * $gate->percent_charge;
       $base_total_charge = $baseFixedCharge + $basePercent_charge;
       // $reduceAbleTotal = $amount + $base_total_charge;
       $reduceAbleTotal = $amount;
       if( $reduceAbleTotal > $userWallet->balance){
           return back()->with(['error' => ['Insufficient Balance']]);
       }
       $data1['user_id']= $user->id;
       $data1['gateway_name']= $gate->gateway->name;
       $data1['gateway_type']= $gate->gateway->type;
       $data1['wallet_id']= $userWallet->id;
       $data1['trx_id']= 'MO'.getTrxNum();
       $data1['amount'] =  $amount;
       $data1['base_cur_charge'] = $base_total_charge;
       $data1['base_cur_rate'] = $baseCurrency->rate;
       $data1['gateway_id'] = $gate->gateway->id;
       $data1['gateway_currency_id'] = $gate->id;
       $data1['gateway_currency'] = strtoupper($gate->currency_code);
       $data1['gateway_percent_charge'] = $percent_charge;
       $data1['gateway_fixed_charge'] = $fixedCharge;
       $data1['gateway_charge'] = $charge;
       $data1['gateway_rate'] = $gate->rate;
       $data1['conversion_amount'] = $conversion_amount;
       $data1['will_get'] = $will_get;
       $data1['payable'] = $reduceAbleTotal;
       session()->put('moneyoutData', $data1);
       $moneyOutData = (object) $data1;
       //Get gateway
       //$gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
       $gateway = $gate->gateway;
       $credentials = $gateway->credentials;
       $data = null;
       $secret_key = getPaymentCredentials($credentials,'Secret key');
       $base_url = getPaymentCredentials($credentials,'Base Url');
       $callback_url = getPaymentCredentials($credentials,'Callback Url');
       $ch = curl_init();
       $url =  $base_url.'/transfers';
       $reference = generateTransactionReference();
       $data = [
           "account_bank" => $request->bank_name,
           "account_number" => $request->account_number,
           "amount" => $will_get,
           "narration" => "Withdraw from wallet",
           "currency" =>$moneyOutData->gateway_currency,
           "reference" => $reference,
           "callback_url" => $callback_url,
           "debit_currency" => $moneyOutData->gateway_currency
       ];
       $headers = [
           'Authorization: Bearer '.$secret_key,
           'Content-Type: application/json'
       ];

       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_POST, true);
       curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
       curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

       $response = curl_exec($ch);
       if (curl_errno($ch)) {
           return back()->with(['error' => [curl_error($ch)]]);
       } else {
           $result = json_decode($response,true);
           if($result['status'] && $result['status'] == 'success'){
               try{
                   //send notifications
                   $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values = null);
                   $this->insertChargesAutomatic($moneyOutData,$inserted_id);
                   $this->insertDeviceManual($moneyOutData,$inserted_id);
                   $nt = Transaction::find($inserted_id);
                   $nt->ref = $reference;
                   $nt->save();
                   session()->forget('moneyoutData');
                   if( $basic_setting->email_notification == true){
                       $user->notify(new WithdrawMail($user,$moneyOutData));
                   }
                   return redirect()->route("user.money.out.index")->with(['success' => ['Withdraw money request is processing and would be completed soon. ']]);
               }catch(Exception $e) {
                   return back()->with(['error' => [$e->getMessage()]]);
               }

           }else{
               return back()->with(['error' => [$result['message']]]);
           }
       }

       curl_close($ch);
        return back()->with(['error' => ["Invalid request,please try again later"]]);
       
    }

   public function paymentInsert(Request $request){
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'gateway' => 'required'
        ]);
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('user.profile.index')->with(['error' => [__('Please submit kyc information!')]]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('user.profile.index')->with(['error' => [__('Please wait before admin approved your kyc information')]]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('user.profile.index')->with(['error' => [__('Admin rejected your kyc information, Please re-submit again')]]);
            }
        }

        $userWallet = UserWallet::where('user_id',$user->id)->where('status',1)->first();
        $gate =PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->where('alias',$request->gateway)->first();

        if (!$gate) {
            return back()->with(['error' => [__("Gateway is not available right now! Please contact with system administration")]]);
        }
        $baseCurrency = Currency::default();
        if (!$baseCurrency) {
            return back()->with(['error' => [__("Default currency not found")]]);
        }
        $amount = $request->amount;

        $min_limit =  $gate->min_limit / $gate->rate;
        $max_limit =  $gate->max_limit / $gate->rate;
        if($amount < $min_limit || $amount > $max_limit) {
            return back()->with(['error' => [__("Please follow the transaction limit")]]);
        }
        //gateway charge
        $fixedCharge = $gate->fixed_charge;
        $percent_charge =  (((($request->amount * $gate->rate)/ 100) * $gate->percent_charge));
        $charge = $fixedCharge + $percent_charge;
        $conversion_amount = $request->amount * $gate->rate;
        $will_get = $conversion_amount -  $charge;

        //base_cur_charge
        $baseFixedCharge = $gate->fixed_charge *  $baseCurrency->rate;
        $basePercent_charge = ($request->amount / 100) * $gate->percent_charge;
        $base_total_charge = $baseFixedCharge + $basePercent_charge;
        // $reduceAbleTotal = $amount + $base_total_charge;
        $reduceAbleTotal = $amount;
        if( $reduceAbleTotal > $userWallet->balance){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        $data['user_id']= $user->id;
        $data['gateway_name']= $gate->gateway->name;
        $data['gateway_type']= $gate->gateway->type;
        $data['wallet_id']= $userWallet->id;
        $data['trx_id']= 'MO'.getTrxNum();
        $data['amount'] =  $amount;
        $data['base_cur_charge'] = $base_total_charge;
        $data['base_cur_rate'] = $baseCurrency->rate;
        $data['gateway_id'] = $gate->gateway->id;
        $data['gateway_currency_id'] = $gate->id;
        $data['gateway_currency'] = strtoupper($gate->currency_code);
        $data['gateway_percent_charge'] = $percent_charge;
        $data['gateway_fixed_charge'] = $fixedCharge;
        $data['gateway_charge'] = $charge;
        $data['gateway_rate'] = $gate->rate;
        $data['conversion_amount'] = $conversion_amount;
        $data['will_get'] = $will_get;
        $data['payable'] = $reduceAbleTotal;
        session()->put('moneyoutData', $data);
        return redirect()->route('user.money.out.preview');
   }
   public function preview(){
    $moneyOutData = (object)session()->get('moneyoutData');
    $moneyOutDataExist = session()->get('moneyoutData');
    if($moneyOutDataExist  == null){
        return redirect()->route('user.money.out.index');
    }
    $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
    if($gateway->type == "AUTOMATIC"){
        $page_title = "Withdraw Via ".$gateway->name;
        if(strtolower($gateway->name) == "flutterwave"){
            $credentials = $gateway->credentials;
            $data = null;
            foreach ($credentials as $object) {
                $object = (object)$object;
                if ($object->label === "Secret key") {
                    $data = $object;
                    break;
                }
            }
            $countries = get_all_countries();
            $currency =  $moneyOutData->gateway_currency;
            $country = Collection::make($countries)->first(function ($item) use ($currency) {
                return $item->currency_code === $currency;
            });

            $allBanks = getFlutterwaveBanks($country->iso2);
            return view('user.sections.money-out.automatic.'.strtolower($gateway->name),compact('page_title','gateway','moneyOutData','allBanks'));
        }else{
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
    }else{
        $page_title = __("Withdraw Via")." ".$gateway->name;
        return view('user.sections.money-out.preview',compact('page_title','gateway','moneyOutData'));

    }


   }
   public function confirmMoneyOut(Request $request){
    $basic_setting = BasicSettings::first();
    $moneyOutData = (object)session()->get('moneyoutData');
    $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
    $payment_fields = $gateway->input_fields ?? [];

    $validation_rules = $this->generateValidationRules($payment_fields);
    $payment_field_validate = Validator::make($request->all(),$validation_rules)->validate();
    $get_values = $this->placeValueWithFields($payment_fields,$payment_field_validate);
        try{
            //send notifications
            $user = auth()->user();
            $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values);
            $this->insertChargesManual($moneyOutData,$inserted_id);
            $this->insertDeviceManual($moneyOutData,$inserted_id);
            session()->forget('moneyoutData');
            if( $basic_setting->email_notification == true){
                $user->notify(new WithdrawMail($user,$moneyOutData));
            }
            return redirect()->route("user.money.out.index")->with(['success' => ['Withdraw money request send to admin successful']]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

   }
   public function confirmMoneyOutAutomatic(Request $request){
    $basic_setting = BasicSettings::first();
    if($request->gateway_name == 'flutterwave'){
        $request->validate([
            'bank_name' => 'required|numeric|gt:0',
            'account_number' => 'required'
        ]);
        $moneyOutData = (object)session()->get('moneyoutData');
        $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();

        $credentials = $gateway->credentials;
        $data = null;
        $secret_key = getPaymentCredentials($credentials,'Secret key');
        $base_url = getPaymentCredentials($credentials,'Base Url');
        $callback_url = getPaymentCredentials($credentials,'Callback Url');
        $ch = curl_init();
        $url =  $base_url.'/transfers';
        $data = [
            "account_bank" => $request->bank_name,
            "account_number" => $request->account_number,
            "amount" => $moneyOutData->will_get,
            "narration" => "Withdraw from wallet",
            "currency" =>$moneyOutData->gateway_currency,
            "reference" => generateTransactionReference(),
            "callback_url" => $callback_url,
            "debit_currency" => $moneyOutData->gateway_currency
        ];
        $headers = [
            'Authorization: Bearer '.$secret_key,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return back()->with(['error' => [curl_error($ch)]]);
        } else {
            $result = json_decode($response,true);
            if($result['status'] && $result['status'] == 'success'){
                try{
                    //send notifications
                    $user = auth()->user();
                    $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values = null);
                    $this->insertChargesAutomatic($moneyOutData,$inserted_id);
                    $this->insertDeviceManual($moneyOutData,$inserted_id);
                    session()->forget('moneyoutData');
                    if( $basic_setting->email_notification == true){
                        $user->notify(new WithdrawMail($user,$moneyOutData));
                    }
                    return redirect()->route("user.money.out.index")->with(['success' => [__('Withdraw money request send successful')]]);
                }catch(Exception $e) {
                    return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
                }

            }else{
                return back()->with(['error' => [$result['message']]]);
            }
        }

        curl_close($ch);

    }else{
        return back()->with(['error' => [__("Invalid request,please try again later")]]);
    }


   }

   //check flutterwave banks
   public function checkBanks(Request $request){
    $bank_account = $request->account_number;
    $bank_code = $request->bank_code;
    $exist['data'] = (checkBankAccount($secret_key = null,$bank_account,$bank_code));
    return response( $exist);
   }
    //validate account
    function validateAccDetails(Request $request){
        $bank_account = $request->number;
        $bank_code = $request->bank;
        $exist = (checkBankAccount($bank_account,$bank_code));
        return response( $exist);
    }
    public function insertRecordManual($moneyOutData,$gateway,$get_values) {
        if($moneyOutData->gateway_type == "AUTOMATIC"){
            $status = 1;
        }else{
            $status = 2;
        }
        $trx_id = $moneyOutData->trx_id ??'MO'.getTrxNum();
        $authWallet = UserWallet::where('id',$moneyOutData->wallet_id)->where('user_id',$moneyOutData->user_id)->first();
        $afterCharge = ($authWallet->balance - ($moneyOutData->amount));
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => auth()->user()->id,
                'user_wallet_id'                => $moneyOutData->wallet_id,
                'payment_gateway_currency_id'   => $moneyOutData->gateway_currency_id,
                'type'                          => PaymentGatewayConst::TYPEMONEYOUT,
                'trx_id'                        => $trx_id,
                'request_amount'                => $moneyOutData->amount,
                'payable'                       => $moneyOutData->will_get,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEMONEYOUT," ")) . " by " .$gateway->name,
                'details'                       => json_encode($get_values),
                'status'                        => $status,
                'created_at'                    => now(),
            ]);
            $this->updateWalletBalanceManual($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }

    public function updateWalletBalanceManual($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertChargesManual($moneyOutData,$id) {

        if(Auth::guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $moneyOutData->gateway_percent_charge,
                'fixed_charge'      => $moneyOutData->gateway_fixed_charge,
                'total_charge'      => $moneyOutData->gateway_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => __("Withdraw Money"),
                'message'       => __("Your Withdraw Request Send To Admin")." " .$moneyOutData->amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            DB::commit();

             //Push Notifications
             event(new UserNotificationEvent($notification_content,$user));
             send_push_notification(["user-".$user->id],[
                 'title'     => $notification_content['title'],
                 'body'      => $notification_content['message'],
                 'icon'      => $notification_content['image'],
             ]);

            //admin notification
            $notification_content['title'] = __("Withdraw Request Send ").' '.$moneyOutData->amount.' '.get_default_currency_code().' '.__("By").' '.$moneyOutData->gateway_name.' '.$moneyOutData->gateway_currency.' ('.$user->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
    public function insertChargesAutomatic($moneyOutData,$id) {

        if(Auth::guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $moneyOutData->gateway_percent_charge,
                'fixed_charge'      => $moneyOutData->gateway_fixed_charge,
                'total_charge'      => $moneyOutData->gateway_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => __("Withdraw Money"),
                'message'       => __("Your Withdraw Request")." " .$moneyOutData->amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            DB::commit();

             //Push Notifications
             event(new UserNotificationEvent($notification_content,$user));
             send_push_notification(["user-".$user->id],[
                 'title'     => $notification_content['title'],
                 'body'      => $notification_content['message'],
                 'icon'      => $notification_content['image'],
             ]);

            //admin notification
            $notification_content['title'] = __('Withdraw Request ').' '.$moneyOutData->amount.' '.get_default_currency_code().' '.__("By").' '.$moneyOutData->gateway_name.' '.$moneyOutData->gateway_currency.' '.__("Successful").' ('.$user->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }

    public function insertDeviceManual($output,$id) {
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
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
     
    //Flutterwave Callback
    public function flutterWebhookNotification(Request $request){
        $logFile = 'public/flutterwebhook_log.txt';
        $logMessage = json_encode($request->all(), JSON_PRETTY_PRINT);
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $input = $request->all();
        $gateway = PaymentGateway::where('type',"AUTOMATIC")->where('alias','flutterwave-money-out')->first();
        $d['gateway'] = $gateway;
        $credentials = $this->getFlutterCredentials($d);
        if($input['event.type'] == 'Transfer' && $input['event'] == 'transfer.completed'){
            //verify trx
            $ref = $input['data']['reference'];
            $trx = Transaction::where('ref', $ref)->first();
            if(!$trx) return ['status' => 'error'];
            $trx;
            if($input['data']['status'] == 'SUCCESSFUL'){
                $trx->status = 1;
                $trx->save();
            }else{
                if($trx->status != 4){
                    $returnAmount = $trx->request_amount;
                    if($trx->user_id != null) {
                        $userWallet = UserWallet::where('user_id',$trx->user_id)->first();
                        $userWallet->balance +=  $returnAmount;
                        $userWallet->save();
                    }else if($trx->merchant_id != null) {
                        $userWallet = MerchantWallet::where('merchant_id',$trx->merchant_id)->first();
                        $userWallet->balance +=  $returnAmount;
                        $userWallet->save();
                    }
                }
                $trx->status = 4;
                $trx->save();
                //refund
                
            }
            return ['status' => 'success'];
        }
        return $request;
    }
    
}
