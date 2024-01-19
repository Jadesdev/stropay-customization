<?php

namespace App\Traits\PaymentGateway;
use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Carbon;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Constants\NotificationConst;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\BasicSettings;
use App\Notifications\User\AddMoney\ApprovedMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Session;
use App\Events\User\NotificationEvent as UserNotificationEvent;

trait MonnifyTrait
{
    public function monnifyInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getMonnifyCredentials($output);
        $reference = generateTransactionReference();
        $amount = $output['amount']->total_amount ? number_format($output['amount']->total_amount,2,'.','') : 0;
        $currency = $output['currency']['currency_code']??"NGN";

        if(auth()->guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
            $user_email = $user->email;
            $user_phone = $user->full_mobile ?? '';
            $user_name = $user->firstname.' '.$user->lastname ?? '';
        }
        $return_url = route('user.add.money.monnify.success');
        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->demo_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        $data = [
            'amount' => $amount,
            'customerEmail' => $user_email,
            'customerName' => $user_name,
            'paymentReference' => $reference,
            'currencyCode' =>  $output['currency']['currency_code']??"NGN",
            'paymentDescription' =>  "Add Money " .dateFormat('d M Y', Carbon::now()),
            'paymentMethods' => ["CARD"],
            'redirectUrl' => $return_url ,
            "contractCode" => $credentials->contract_code,
            
        ];
        
        $response = Http::withHeaders([
            'Authorization' => $this->getHeader($credentials)
        ])->post($link_url.'/v1/merchant/transactions/init-transaction', $data)->json();
        
        if ($response['responseMessage'] !== 'success' && $response['requestSuccessful'] !== 'true') {
            throw new Exception("Payment not Initalized. Please try again");
        }
        // return $response;
        $this->monnifyJunkInsert($data);
        
        return redirect($response['responseBody']['checkoutUrl']);
      
    }

    public function monnifyJunkInsert($response){
         $output = $this->output;
        $user = auth()->guard(get_auth_guard())->user();
        $creator_table = $creator_id = $wallet_table = $wallet_id = null;

        $creator_table = auth()->guard(get_auth_guard())->user()->getTable();
        $creator_id = auth()->guard(get_auth_guard())->user()->id;
        $wallet_table = $output['wallet']->getTable();
        $wallet_id = $output['wallet']->id;
        
        $data = [
            'gateway'      => $output['gateway']->id,
            'currency'     => $output['currency']->id,
            'amount'       => json_decode(json_encode($output['amount']),true),
            'response'     => $response,
            'wallet_table'  => $wallet_table,
            'wallet_id'     => $wallet_id,
            'creator_table' => $creator_table,
            'creator_id'    => $creator_id,
            'creator_guard' => get_auth_guard(),
        ];


        Session::put('identifier',$response['paymentReference']);
        Session::put('output',$output);
        
        return TemporaryData::create([
            'type'          => PaymentGatewayConst::MONNIFY,
            'identifier'    => $response['paymentReference'],
            'data'          => $data,
        ]);

    }
    
    public function monnifySuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');
         return $this->createTransactionMonnify($output);
    }
    public function createTransactionMonnify($output){
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        $trx_id = 'AM'.getTrxNum();
        $inserted_id = $this->insertRecordMonnify($output,$trx_id);
        $this->insertChargesMonnify($output,$inserted_id);
        $this->insertDeviceMonnify($output,$inserted_id);
        $this->removeTempDataMonnify($output);
        
        if($this->requestIsApiUser()) {
            // logout user
            $api_user_login_guard = $this->output['api_login_guard'] ?? null;
            if($api_user_login_guard != null) {
                auth()->guard($api_user_login_guard)->logout();
            }
        }
        if( $basic_setting->email_notification == true){
            $user->notify(new ApprovedMail($user,$output,$trx_id));
        }
        
    }
    public function insertRecordMonnify($output,$trx_id) {
        $token = $this->output['tempData']['identifier'] ?? "";
        DB::beginTransaction();
        try{
            if(Auth::guard(get_auth_guard())->check()){
                $user_id = auth()->guard(get_auth_guard())->user()->id;
            }

                // Add money
                $id = DB::table("transactions")->insertGetId([
                    'user_id'                       => $user_id,
                    'user_wallet_id'                => $output['wallet']->id,
                    'payment_gateway_currency_id'   => $output['currency']->id,
                    'type'                          => $output['type'],
                    'trx_id'                        => $trx_id,
                    'request_amount'                => $output['amount']->requested_amount,
                    'payable'                       => $output['amount']->total_amount,
                    'available_balance'             => $output['wallet']->balance + $output['amount']->requested_amount,
                    'remark'                        => ucwords(remove_speacial_char($output['type']," ")) . " With " . $output['gateway']->name,
                    'details'                       => 'Monnify Payment Successfull',
                    'status'                        => true,
                    'created_at'                    => now(),
                ]);
                $this->updateWalletBalanceMonnify($output);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    
    public function updateWalletBalanceMonnify($output) {
        $update_amount = $output['wallet']->balance + $output['amount']->requested_amount;

        $output['wallet']->update([
            'balance'   => $update_amount,
        ]);
    }
    public function insertChargesMonnify($output,$id) {
        if(Auth::guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $output['amount']->percent_charge,
                'fixed_charge'      => $output['amount']->fixed_charge,
                'total_charge'      => $output['amount']->total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Add Money",
                'message'       => "Your Wallet (".$output['wallet']->currency->code.") balance  has been added ".$output['amount']->requested_amount.' '. $output['wallet']->currency->code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  auth()->user()->id,
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
             $notification_content['title'] = 'Add Money '.$output['amount']->requested_amount.' '.$output['amount']->default_currency.' By '. $output['currency']->name.' ('.$user->username.')';
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
    }
    
    public function insertDeviceMonnify($output,$id) {
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
    }

    public function removeTempDataMonnify($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }

    
    public function getMonnifyCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $public_key_sample = ['api key','api_key','client id','primary key', 'public key'];
        $secret_key_sample = ['client_secret','client secret','secret','secret key','secret id'];
        $contract_code_sample = ['contract_code','contract code'];

        $public_key = '';
        $outer_break = false;

        foreach($public_key_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->monnifyPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->monnifyPlainText($label);
                if($label == $modify_item) {
                    $public_key = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $secret_key = '';
        $outer_break = false;
        foreach($secret_key_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->monnifyPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->monnifyPlainText($label);

                if($label == $modify_item) {
                    $secret_key = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $contract_code = '';
        $outer_break = false;
        foreach($contract_code_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->monnifyPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->monnifyPlainText($label);

                if($label == $modify_item) {
                    $contract_code = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        
        $mode = $gateway->env;

        $paypal_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if(array_key_exists($mode,$paypal_register_mode)) {
            $mode = $paypal_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }
        return (object) [
            'public_key'     => $public_key,
            'secret_key'     => $secret_key,
            'contract_code' => $contract_code,
            'demo_url' => "https://sandbox.monnify.com/api",
            'live_url' => "https://api.monnify.com/api",
            'mode' => $mode
        ];
    }
    
    public function monnifyPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }
    public function getHeader($credentials)
    {
        $data = base64_encode($credentials->public_key.':'.$credentials->secret_key);
        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->demo_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$data
        ])->post($link_url.'/v1/auth/login' );

        return 'Bearer ' . $response['responseBody']['accessToken'];
    }
    
    public function verifyMonnifyTrx($data){
        $output = Session::get('output');
        $credentials = $this->getMonnifyCredentials($output);
        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->demo_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        $response = Http::withHeaders([
            'Authorization' => $this->getHeader($credentials)
        ])->get($link_url.'/v1/merchant/transactions/query', $data)->json();
        if($response['responseMessage'] == 'success' && $response['responseBody']['paymentStatus'] == "PAID"){
            $status = "PAID";
        }else{
            $status = 'FAILED';
        }
        return $status;
    }
    public function verifyMonnifyWebhook($data, $credentials){
       if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->demo_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        $response = Http::withHeaders([
            'Authorization' => $this->getHeader($credentials)
        ])->get($link_url.'/v1/merchant/transactions/query', $data)->json();
        if($response['responseMessage'] == 'success' && $response['responseBody']['paymentStatus'] == "PAID"){
            $status = "PAID";
        }else{
            $status = 'FAILED';
        }
        return $response;
    }

    public function createMonnifyAccounts($data, $credentials){
        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->demo_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        $response = Http::withHeaders([
            'Authorization' => $this->getHeader($credentials)
        ])->post($link_url.'/v2/bank-transfer/reserved-accounts', $data)->json();
        
        return $response;
    }
}
