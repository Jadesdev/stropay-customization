<?php
namespace App\Http\Helpers;

use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\Currency;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\TemporaryData;
use App\Models\Transaction;
use App\Traits\PaymentGateway\Paypal;
use App\Traits\PaymentGateway\Stripe;
use App\Traits\PaymentGateway\Manual;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\PaymentGateway\FlutterwaveTrait;
use App\Traits\PaymentGateway\RazorTrait;
use App\Traits\PaymentGateway\PagaditoTrait;
use App\Traits\PaymentGateway\SslcommerzTrait;
use App\Traits\PaymentGateway\CoinGate;
use Illuminate\Support\Facades\Route;
use App\Traits\PaymentGateway\Tatum;
use App\Models\Admin\PaymentGateway as PaymentGatewayModel;

class PaymentGatewayApi {

    use Paypal,Stripe,Manual,FlutterwaveTrait,RazorTrait,PagaditoTrait,SslcommerzTrait,CoinGate,Tatum;

    protected $request_data;
    protected $output;
    protected $currency_input_name = "currency";
    protected $amount_input = "amount";
    protected $predefined_user_wallet;
    protected $predefined_guard;
    protected $predefined_user;


    public function __construct(array $request_data)
    {
        $this->request_data = $request_data;
    }

    public static function init(array $data) {
        return new PaymentGatewayApi($data);
    }

    public function gateway() {
        $request_data = $this->request_data;

        if(empty($request_data)){
            $error = ['error'=>[__('Gateway Information is not available. Please provide payment gateway currency alias')]];
            return Helpers::error($error);
        }
        $validated = $this->validator($request_data)->validate();
        $gateway_currency = PaymentGatewayCurrency::where("alias",$validated[$this->currency_input_name])->first();

        if(!$gateway_currency || !$gateway_currency->gateway) {

            $error = ['error'=>[__('Gateway not available')]];
            return Helpers::error($error);
        }
        $defualt_currency = Currency::default();
        $user_wallet = $this->getUserWallet($defualt_currency);

        if(!$user_wallet) {
            $this->currency_input_name = __("User wallet not found!");
            $error = ['error'=>[__("User wallet not found!")]];
            return Helpers::error($error);
        }


        if($gateway_currency->gateway->isAutomatic()) {
            $this->output['gateway']    = $gateway_currency->gateway;
            $this->output['currency']   = $gateway_currency;
            $this->output['amount']     = $this->amount();
            $this->output['wallet']     = $user_wallet;
            $this->output['distribute'] = $this->gatewayDistribute($gateway_currency->gateway);
        }elseif($gateway_currency->gateway->isManual()){
            $this->output['gateway']    = $gateway_currency->gateway;
            $this->output['currency']   = $gateway_currency;
            $this->output['amount']     = $this->amount();
            $this->output['wallet']     = $user_wallet;
            $this->output['distribute'] = $this->gatewayDistribute($gateway_currency->gateway);

        }

        // limit validation
        $this->limitValidation($this->output);

        return $this;
    }
    public function getUserWallet($gateway_currency) {

        if($this->predefined_user_wallet) return $this->predefined_user_wallet;

        $guard = get_auth_guard();
        $register_wallets = PaymentGatewayConst::registerWallet();
        if(!array_key_exists($guard,$register_wallets)) {
            $error = ['error'=>[__('Wallet Not Registered. Please register user wallet in PaymentGatewayConst::class with user guard name')]];
            return Helpers::error($error);
        }
        $wallet_model = $register_wallets[$guard];
        $user_wallet = $wallet_model::auth()->whereHas("currency",function($q) use ($gateway_currency){
            $q->where("code",$gateway_currency->code);
        })->first();

        if(!$user_wallet) {
            if(request()->acceptsJson()){
                $error = ['error'=>[$this->currency_input_name = __("User wallet not found!")]];
                return Helpers::error($error);
            }

        }

        return $user_wallet;
    }
    public function validator($data) {
        return Validator::make($data,[
            $this->currency_input_name  => "required|exists:payment_gateway_currencies,alias",
            $this->amount_input         => "required|numeric|gt:0",
        ]);

    }

    public function limitValidation($output) {
        $gateway_currency = $output['currency'];
        $requested_amount = $output['amount']->requested_amount;
        if($requested_amount < ($gateway_currency->min_limit/$gateway_currency->rate) || $requested_amount > ($gateway_currency->max_limit/$gateway_currency->rate)) {

            $error = ['error'=>[__("Please follow the transaction limit")]];
            return Helpers::error($error);
        }
    }

    public function get() {
        return $this->output;
    }

    public function gatewayDistribute($gateway = null) {

        if(!$gateway) $gateway = $this->output['gateway'];
        $alias = Str::lower($gateway->alias);
        if($gateway->type == PaymentGatewayConst::AUTOMATIC){
            $method = PaymentGatewayConst::register($alias);
        }elseif($gateway->type == PaymentGatewayConst::MANUAL){
            $method = PaymentGatewayConst::register(strtolower($gateway->type));
        }

        if(method_exists($this,$method)) {
            return $method;
        }

        $error = ['error'=>["Gateway(".$gateway->name.") Trait or Method (".$method."()) does not exists"]];
        return Helpers::error($error);
    }

    public function amount() {
        $currency = $this->output['currency'] ?? null;
        if(!$currency) {
            $error = ['error'=>[__('Gateway currency not found')]];
            return Helpers::error($error);
        }

        return $this->chargeCalculate($currency);
    }

    public function chargeCalculate($currency,$receiver_currency = null) {

        $amount = $this->request_data[$this->amount_input];
        $sender_currency_rate = $currency->rate;
        ($sender_currency_rate == "" || $sender_currency_rate == null) ? $sender_currency_rate = 0 : $sender_currency_rate;
        ($amount == "" || $amount == null) ? $amount : $amount;

        if($currency != null) {
            $fixed_charges = $currency->fixed_charge;
            $percent_charges = $currency->percent_charge;
        }else {
            $fixed_charges = 0;
            $percent_charges = 0;
        }

        $fixed_charge_calc = ( $fixed_charges);
        $percent_charge_calc = $sender_currency_rate * (($amount / 100 ) * $percent_charges );

        $total_charge = $fixed_charge_calc + $percent_charge_calc;

        if($receiver_currency) {
            $receiver_currency_rate = $receiver_currency->rate;
            ($receiver_currency_rate == "" || $receiver_currency_rate == null) ? $receiver_currency_rate = 0 : $receiver_currency_rate;
            $exchange_rate = ($receiver_currency_rate / $sender_currency_rate);
            $will_get = ($amount * $exchange_rate);

            $data = [
                'requested_amount'          => $amount,
                'sender_cur_code'           => $currency->currency_code,
                'sender_cur_rate'           => $sender_currency_rate ?? 0,
                'receiver_cur_code'         => $receiver_currency->currency_code,
                'receiver_cur_rate'         => $receiver_currency->rate ?? 0,
                'fixed_charge'              => $fixed_charge_calc,
                'percent_charge'            => $percent_charge_calc,
                'total_charge'              => $total_charge,
                'total_amount'              => $amount + $total_charge,
                'exchange_rate'             => $exchange_rate,
                'will_get'                  => $will_get,
                'default_currency'          => get_default_currency_code(),
            ];

        }else {
            $defualt_currency = Currency::default();
            $exchange_rate =  $defualt_currency->rate;
            $will_get = ($amount * $exchange_rate);
            $total_Amount = ($amount * $sender_currency_rate) + $total_charge;

            $data = [
                'requested_amount'          => $amount,
                'sender_cur_code'           => $currency->currency_code,
                'sender_cur_rate'           => $sender_currency_rate ?? 0,
                'fixed_charge'              => $fixed_charge_calc,
                'percent_charge'            => $percent_charge_calc,
                'total_charge'              => $total_charge,
                'total_amount'              => $total_Amount,
                'exchange_rate'             => $exchange_rate,
                'will_get'                  => $will_get,
                'default_currency'          => get_default_currency_code(),
            ];
        }

        return (object) $data;
    }

    public function render() {
        $output = $this->output;

        if(!is_array($output)){
            $error = ['error'=>[__('Render failed! Please call with valid gateway/credentials')]];
            return Helpers::error($error);
        }

        $common_keys = ['gateway','currency','amount','distribute'];
        foreach($output as $key => $item) {
            if(!array_key_exists($key,$common_keys)) {
                $this->gateway();
                break;
            }
        }

        $distributeMethod = $this->output['distribute'];
        return $this->$distributeMethod($output);
    }

    public function responseReceive($type = null) {
        $tempData = $this->request_data;

        if(empty($tempData) || empty($tempData['type'])){
            $error = ['error'=>[__('Transaction failed. Record didn\'t saved properly. Please try again')]];
            return Helpers::error($error);
        }
        if($this->requestIsApiUser()) {
            $creator_table = $tempData['data']->creator_table ?? null;
            $creator_id = $tempData['data']->creator_id ?? null;
            $creator_guard = $tempData['data']->creator_guard ?? null;
            $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
            if($creator_table != null && $creator_id != null && $creator_guard != null) {
                if(!array_key_exists($creator_guard,$api_authenticated_guards)) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
                $creator = DB::table($creator_table)->where("id",$creator_id)->first();
                if(!$creator) throw new Exception(__("Request user doesn\'t save properly. Please try again"));
                $api_user_login_guard = $api_authenticated_guards[$creator_guard];
                $this->output['api_login_guard'] = $api_user_login_guard;
                Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
            }
        }

        $method_name = $tempData['type']."Success";

        $currency_id = $tempData['data']->currency ?? "";
        $gateway_currency = PaymentGatewayCurrency::find($currency_id);
        if(!$gateway_currency){
            $error = ['error'=>[__('Transaction failed. Gateway currency not available')]];
            return Helpers::error($error);
        }
        $requested_amount = $tempData['data']->amount->requested_amount ?? 0;
        $validator_data = [
            $this->currency_input_name => $gateway_currency->alias,
            $this->amount_input        => $requested_amount,
        ];
        $this->request_data = $validator_data;
        $this->gateway();
        $this->output['tempData'] = $tempData;
        if($type == 'flutterWave'){
            if(method_exists(FlutterwaveTrait::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'razorpay'){
            if(method_exists(RazorTrait::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'pagadito'){
            if(method_exists(PagaditoTrait::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'stripe'){
            if(method_exists(Stripe::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'sslcommerz'){
            if(method_exists(SslcommerzTrait::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'coingate'){
            if(method_exists(CoinGate::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }elseif($type == 'tatum'){
            if(method_exists(TATUM::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }else{
            if(method_exists(Paypal::class,$method_name)) {
                return $this->$method_name($this->output);
            }
        }

        $error = ['error'=>["Response method ".$method_name."() does not exists."]];
        return Helpers::error($error);

    }

    public function type($type) {
        $this->output['type']  = $type;
        return $this;
    }
    public function api() {
        $output = $this->output;
        $output['distribute']   = $this->gatewayDistribute() . "Api";
        $method = $output['distribute'];
        $response = $this->$method($output);
        $output['response'] = $response;
        if( $output['distribute'] == "pagaditoInitApi"){
            $parts = parse_url( $output['response']);
                parse_str($parts['query'], $query);
                // Extract the token value
                if (isset($query['token'])) {
                    $tokenValue = $query['token'];
                } else {
                    $tokenValue = '';
                }
            $output['response'] =  $tokenValue;
        }


        $this->output = $output;
        return $this;
    }
    public function requestIsApiUser() {
        $request_source = request()->get('r-source');
        if($request_source != null && $request_source == PaymentGatewayConst::APP) return true;
        return false;
    }

    public static function getValueFromGatewayCredentials($gateway, $keywords) {
        $result = "";
        $outer_break = false;
        foreach($keywords as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = PaymentGateway::makePlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = PaymentGateway::makePlainText($label);

                if($label == $modify_item) {
                    $result = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        return $result;
    }
    public static function makePlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }
    public function setSource(string $source) {
        $sources = [
            'r-source'  => $source,
        ];

        return $sources;
    }

    public function makeUrlParams(array $sources) {
        try{
            $params = http_build_query($sources);
        }catch(Exception $e) {
            throw new Exception(__("Something went wrong! Failed to make URL Params."));
        }
        return $params;
    }

    public function setUrlParams(string $url_params) {
        $output = $this->output;
        if(!$output) throw new Exception(__("Something went wrong! Gateway render failed. Please call gateway() method before calling api() method"));
        if(isset($output['url_params'])) {
            // if already param has
            $params = $this->output['url_params'];
            $update_params = $params . "&" . $url_params;
            $this->output['url_params'] = $update_params; // Update/ reassign URL Parameters
        }else {
            $this->output['url_params']  = $url_params; // add new URL Parameters;
        }
    }

    public function getUrlParams() {
        $output = $this->output;
        if(!$output || !isset($output['url_params'])) $params = "";
        $params = $output['url_params'] ?? "";
        return $params;
    }

    public function setGatewayRoute($route_name, $gateway, $params = null) {
        if(!Route::has($route_name)) throw new Exception('Route name ('.$route_name.') is not defined');
        if($params) {
            return route($route_name,$gateway."?".$params);
        }
        return route($route_name,$gateway);
    }
    public function handleCallback($reference,$callback_data,$gateway_name) {
        if($reference == PaymentGatewayConst::CALLBACK_HANDLE_INTERNAL) {
            $gateway = PaymentGatewayModel::gateway($gateway_name)->first();
            $callback_response_receive_method = $this->getCallbackResponseMethod($gateway);
            return $this->$callback_response_receive_method($callback_data, $gateway);
        }
        $transaction = Transaction::where('callback_ref',$reference)->first();
        $this->output['callback_ref']       = $reference;
        $this->output['capture']            = $callback_data;
        if($transaction) {
            $gateway_currency = $transaction->gateway_currency;
            $gateway = $gateway_currency->gateway;
            $requested_amount = $transaction->request_amount;
            $validator_data = [
                $this->currency_input_name  => $gateway_currency->alias,
                $this->amount_input         => $requested_amount
            ];
            $user_wallet = $transaction->creator_wallet;
            $this->predefined_user_wallet = $user_wallet;
            $this->predefined_guard = $transaction->creator->modelGuardName();
            $this->predefined_user = $transaction->creator;
            $this->output['transaction']    = $transaction;
        }else {
            // find reference on temp table
            $tempData = TemporaryData::where('identifier',$reference)->first();
            if($tempData) {
                $gateway_currency_id = $tempData->data->currency ?? null;
                $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
                if($gateway_currency) {
                    $gateway = $gateway_currency->gateway;
                    $requested_amount = $tempData['data']->amount->requested_amount ?? 0;
                    $validator_data = [
                        $this->currency_input_name  => $gateway_currency->alias,
                        $this->amount_input         => $requested_amount
                    ];
                    $get_wallet_model = PaymentGatewayConst::registerWallet()[$tempData->data->creator_guard];
                    $user_wallet = $get_wallet_model::find($tempData->data->wallet_id);
                    $this->predefined_user_wallet = $user_wallet;
                    $this->predefined_guard = $user_wallet->user->modelGuardName(); // need to update
                    $this->predefined_user = $user_wallet->user;
                    $this->output['tempData'] = $tempData;
                }
            }
        }
        if(isset($gateway)) {

            $this->request_data = $validator_data;
            $this->gateway();
            $callback_response_receive_method = $this->getCallbackResponseMethod($gateway);
            return $this->$callback_response_receive_method($reference, $callback_data, $this->output);
        }
        logger("Gateway not found!!" , [
            "reference"     => $reference,
        ]);
    }

    public function getCallbackResponseMethod($gateway) {
        $gateway_is = PaymentGatewayConst::registerGatewayRecognization();
        foreach($gateway_is as $method => $gateway_name) {
            if(method_exists($this,$method)) {
                if($this->$method($gateway)) {
                    return $this->generateCallbackMethodName($gateway_name);
                    break;
                }
            }
        }

    }
    public function generateCallbackMethodName(string $name) {
        return $name . "CallbackResponse";
    }

    public function generateSuccessMethodName(string $name) {
        return $name . "Success";
    }
    public function searchWithReferenceInTransaction($reference) {
        $transaction = DB::table('transactions')->where('callback_ref',$reference)->first();
        if($transaction) {
            return $transaction;
        }
        return false;
    }

}
