<?php

namespace App\Http\Middleware;

use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use Closure;
use App\Providers\Admin\BasicSettingsProvider;

class KycApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $basic_settings = BasicSettingsProvider::get();
        if($basic_settings->kyc_verification) {
            $user = auth()->user();
            if($user->kyc_verified === GlobalConst::DEFAULT) {
                return  Response::error([__('Please submit kyc information!')]);
            }else if($user->kyc_verified == GlobalConst::PENDING) {
                return  Response::error([__('Your KYC information is pending. Please wait for admin confirmation.')]);
            }elseif($user->kyc_verified == GlobalConst::REJECTED){
                return  Response::error([__('Your KYC information is rejected. Please submit again your information to admin.')]);
            }
        }
        return $next($request);
    }
}
