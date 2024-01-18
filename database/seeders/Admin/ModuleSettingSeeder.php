<?php

namespace Database\Seeders\Admin;

use App\Constants\ModuleSetting;
use App\Models\Admin\ModuleSetting as AdminModuleSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModuleSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         //make module for user
        $data = [
            ModuleSetting::SEND_MONEY               => 'Send Money',
            ModuleSetting::RECEIVE_MONEY            => 'Receive Money',
            ModuleSetting::REMITTANCE_MONEY         => 'Remittance Money',
            ModuleSetting::ADD_MONEY                => 'Add Money',
            ModuleSetting::WITHDRAW_MONEY           => 'Withdraw Money',
            ModuleSetting::MAKE_PAYMENT             => 'Make Payment',
            ModuleSetting::VIRTUAL_CARD             => 'Virtual Card',
            ModuleSetting::BILL_PAY                 => 'Bill Pay',
            ModuleSetting::MOBILE_TOPUP             => 'Mobile Topup',
            ModuleSetting::REQUEST_MONEY            => 'Request Money',
            ModuleSetting::PAY_LINK                 => 'Pay Link'

        ];
        $create = [];
        foreach($data as $slug => $item) {
            $create[] = [
                'admin_id'          => 1,
                'slug'              => $slug,
                'user_type'         => "USER",
                'status'            => true,
                'created_at'        => now(),
            ];
        }
        AdminModuleSetting::insert($create);
         //make module for merchant
        $data = [
            ModuleSetting::MERCHANT_RECEIVE_MONEY               => 'Merchant Receive Money',
            ModuleSetting::MERCHANT_WITHDRAW_MONEY              => 'Merchant Withdraw Money',
            ModuleSetting::MERCHANT_APIKEY                      => 'Merchant API Key',
            ModuleSetting::MERCHANT_GATEWAY                     => 'Merchant Gateway Settings',
            ModuleSetting::MERCHANT_PAY_LINK                    => 'Merchant Pay Link'

        ];
        $create = [];
        foreach($data as $slug => $item) {
            $create[] = [
                'admin_id'          => 1,
                'slug'              => $slug,
                'user_type'         => "MERCHANT",
                'status'            => true,
                'created_at'        => now(),
            ];
        }
        AdminModuleSetting::insert($create);
    }
}
