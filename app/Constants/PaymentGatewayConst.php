<?php
namespace App\Constants;
use App\Models\UserWallet;
use Illuminate\Support\Str;

class PaymentGatewayConst {

    const AUTOMATIC = "AUTOMATIC";
    const MANUAL    = "MANUAL";
    const ADDMONEY  = "Add Money";
    const MONEYOUT  = "Money Out";
    const ACTIVE    =  true;

    const FIAT                      = "FIAT";
    const CRYPTO                    = "CRYPTO";
    const CRYPTO_NATIVE             = "CRYPTO_NATIVE";
    const ASSET_TYPE_WALLET         = "WALLET";
    const CALLBACK_HANDLE_INTERNAL  = "CALLBACK_HANDLE_INTERNAL";

    const NOT_USED  = "NOT-USED";
    const USED      = "USED";
    const SENT      = "SENT";

    const LINK_TYPE_PAY = 'pay';
    const LINK_TYPE_SUB = 'sub';

    const TYPEADDMONEY      = "ADD-MONEY";
    const TYPEMONEYOUT      = "MONEY-OUT";
    const TYPEWITHDRAW      = "WITHDRAW";
    const TYPECOMMISSION    = "COMMISSION";
    const TYPEBONUS         = "BONUS";
    const TYPETRANSFERMONEY = "TRANSFER-MONEY";
    const SENDREMITTANCE = "REMITTANCE";
    const RECEIVEREMITTANCE = "RECEIVE-REMITTANCE";
    const TYPEMONEYEXCHANGE = "MONEY-EXCHANGE";
    const BILLPAY = "BILL-PAY";
    const MOBILETOPUP = "MOBILE-TOPUP";
    const VIRTUALCARD = "VIRTUAL-CARD";
    const CARDBUY = "CARD-BUY";
    const CARDFUND = "CARD-FUND";
    const REQUESTMONEY   = "REQUEST-MONEY";
    const TYPEPAYLINK            = "PAY-LINK";
    const TYPEADDSUBTRACTBALANCE = "ADD-SUBTRACT-BALANCE";
    const TYPEMAKEPAYMENT = "MAKE-PAYMENT";


    const STATUSSUCCESS     = 1;
    const STATUSPENDING     = 2;
    const STATUSHOLD        = 3;
    const STATUSREJECTED    = 4;
    const STATUSWAITING             = 5;

    const PAYPAL = 'paypal';
    const STRIPE = 'stripe';
    const MANUA_GATEWAY = 'manual';
    const FLUTTER_WAVE = 'flutterwave';
    const RAZORPAY = 'razorpay';
    const PAGADITO = 'pagadito';
    const SSLCOMMERZ = 'sslcommerz';
    const COINGATE = 'coingate';
    const TATUM   = 'tatum';


    const SEND = "SEND";
    const RECEIVED = "RECEIVED";
    const PENDING = "PENDING";
    const REJECTED = "REJECTED";
    const CREATED = "CREATED";
    const SUCCESS = "SUCCESS";
    const EXPIRED = "EXPIRED";

    const ENV_SANDBOX       = "SANDBOX";
    const ENV_PRODUCTION    = "PRODUCTION";

    //merchant payment type
    const MERCHANTPAYMENT       ="MERCHANT-PAYMENT";
    const WALLET                = "WALLET";
    const VIRTUAL               = "VIRTUAL-CARD";
    const MASTER                = "MASTER-CARD";



    public static function add_money_slug() {
        return Str::slug(self::ADDMONEY);
    }

    public static function paylink_slug(){
        return Str::slug(self::TYPEPAYLINK);
    }

    public static function money_out_slug() {
        return Str::slug(self::MONEYOUT);
    }

    public static function register($alias = null) {
        $gateway_alias  = [
            self::PAYPAL => "paypalInit",
            self::STRIPE => "stripeInit",
            self::MANUA_GATEWAY => "manualInit",
            self::FLUTTER_WAVE => 'flutterwaveInit',
            self::RAZORPAY => 'razorInit',
            self::PAGADITO => 'pagaditoInit',
            self::SSLCOMMERZ => 'sslcommerzInit',
            self::COINGATE  => 'coingateInit',
            self::TATUM         => 'tatumInit'
        ];

        if($alias == null) {
            return $gateway_alias;
        }

        if(array_key_exists($alias,$gateway_alias)) {
            return $gateway_alias[$alias];
        }
        return "init";
    }
    const APP       = "APP";
    public static function apiAuthenticateGuard() {
            return [
                'api'   => 'web',
            ];
    }
    public static function registerWallet() {
        return [
            'web'       => UserWallet::class,
            'api'       => UserWallet::class,
        ];
    }
    public static function registerGatewayRecognization() {
        return [
            'isCoinGate'    => self::COINGATE,
            'isTatum'       => self::TATUM,
        ];
    }

}
