<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\PaymentGateway;
use App\Traits\PaymentGateway\MonnifyTrait;
use Illuminate\Http\Request;
use Str;
class ReceiveMoneyController extends Controller
{
    use MonnifyTrait;

    public function index() {
        $page_title = __("Receive Money");
        $merchant = auth()->user();
        $merchant->createQr();
        $merchantQrCode = $merchant->qrCode()->first();
        $uniqueCode = $merchantQrCode->qr_code??'';
        $qrCode = generateQr($uniqueCode);

        $banks = auth()->user()->monnify_banks;
        $banks = \json_decode($banks);
        return view('merchant.sections.receive-money.index',compact("page_title","uniqueCode","qrCode",'merchant','banks'));
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
        $reference = $user['username'].Str::random(9);
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
}
