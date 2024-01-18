<?php

namespace Database\Seeders\Update;

use Illuminate\Database\Seeder;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\PaymentGatewayCurrency;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //TATUM
        $tatum_id = PaymentGateway::latest()->first();
        if(!PaymentGateway::where('alias','tatum')->exists()){
            $payment_gateways_id = $tatum_id->id+1;
            $payment_gateways_code = PaymentGateway::max('code')+5;

            $payment_gateways = array(
                array('id' => $payment_gateways_id,'slug' => 'add-money','code' => $payment_gateways_code,'type' => 'AUTOMATIC','name' => 'Tatum','title' => 'Tatum Gateway','alias' => 'tatum','image' => '857aeca5-e62c-45a1-b479-5e42eb7d746a.webp','credentials' => '[{"label":"Testnet","placeholder":"Enter Testnet","name":"test-net","value":"t-65897d8e7a5ea0001c560728-38b8f1e3b6db44e8a804b118"},{"label":"Mainnet","placeholder":"Enter Mainnet","name":"main-net","value":"t-65897d8e7a5ea0001c560728-da35c7040de94dabbfbeaef7"}]','supported_currencies' => '["BTC","ETH","SOL"]','crypto' => '1','desc' => NULL,'input_fields' => NULL,'status' => '1','last_edit_by' => '1','created_at' => now(),'updated_at' => now(),'env' => 'SANDBOX')
            );
            PaymentGateway::insert($payment_gateways);

            $payment_gateway_currencies = array(
                array('payment_gateway_id' =>  $payment_gateways_id,'name' => 'Tatum BTC','alias' => 'tatum-btc-automatic','currency_code' => 'BTC','currency_symbol' => 'BTC','image' => NULL,'min_limit' => '0.00000000','max_limit' => '1000.00000000','percent_charge' => '1.00000000','fixed_charge' => '0.00000000','rate' => '0.00002400','created_at' => now(),'updated_at' => now()),
                array('payment_gateway_id' =>  $payment_gateways_id,'name' => 'Tatum ETH','alias' => 'tatum-eth-automatic','currency_code' => 'ETH','currency_symbol' => 'ETH','image' => NULL,'min_limit' => '0.00000000','max_limit' => '1000.00000000','percent_charge' => '1.00000000','fixed_charge' => '0.00000000','rate' => '0.00044000','created_at' => now(),'updated_at' => now()),
                array('payment_gateway_id' =>  $payment_gateways_id,'name' => 'Tatum SOL','alias' => 'tatum-sol-automatic','currency_code' => 'SOL','currency_symbol' => 'SOL','image' => NULL,'min_limit' => '0.00000000','max_limit' => '1000.00000000','percent_charge' => '1.00000000','fixed_charge' => '0.00000000','rate' => '3.76000000','created_at' => now(),'updated_at' => now())
            );
            PaymentGatewayCurrency::insert($payment_gateway_currencies);

        }

    }
}
