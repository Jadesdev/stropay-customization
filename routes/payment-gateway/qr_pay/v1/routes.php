<?php

use App\Http\Controllers\PaymentGateway\QrPay\v1\AuthenticationController;
use App\Http\Controllers\PaymentGateway\QrPay\v1\PaymentController;
use App\Http\Controllers\PaymentGateway\QrPay\v1\UserAuthenticationController;
use App\Http\Controllers\Merchant\ApiBillPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('pay')->name('qrpay.pay.')->group(function(){
    // For API Uses (Sandbox)
    Route::prefix('sandbox/api/v1')->group(function(){
        Route::controller(AuthenticationController::class)->prefix('authentication')->group(function(){
           Route::post('token','generateToken');
        });

        Route::controller(PaymentController::class)->prefix('payment')->group(function(){
            Route::post('create','paymentCreate');
        });
    });

    // For API Uses (Production)
    Route::prefix('api/v1')->group(function(){
        Route::controller(AuthenticationController::class)->prefix('authentication')->group(function(){
           Route::post('token','generateToken');
        });

        Route::controller(PaymentController::class)->prefix('payment')->group(function(){
            Route::post('create','paymentCreate');
        });
    });


    // For WEB Uses (Sandbox)
    Route::prefix('sandbox/v1')->name('sandbox.v1.')->middleware(['web'])->group(function(){

        Route::prefix('user')->name('user.')->group(function(){

            Route::controller(UserAuthenticationController::class)->prefix('authentication')->name('auth.')->group(function(){
                Route::get('form/{token}','showAuthForm')->name('form');
                Route::post('form/submit/{token}','authFormSubmit')->name('form.submit');
                Route::get('mail/verify/{token}','showMailVerify')->name('mail.verify.form');
                Route::post('mail/verify/submit/{token}','mailVerifySubmit')->name('mail.verify.form.submit');
            });

            Route::controller(PaymentController::class)->prefix('payment')->name('payment.')->group(function(){
                Route::get('preview/{token}','paymentPreview')->name('preview');
                Route::post('preview/submit/{token}','paymentConfirm')->name('preview.submit');
            });

        });

    });

    // For WEB Uses (Production)
    Route::prefix('v1')->name('v1.')->middleware(['web'])->group(function(){

        Route::prefix('user')->name('user.')->group(function(){

            Route::controller(UserAuthenticationController::class)->prefix('authentication')->name('auth.')->group(function(){
                Route::get('form/{token}','showAuthForm')->name('form');
                Route::post('form/submit/{token}','authFormSubmit')->name('form.submit');
                Route::get('mail/verify/{token}','showMailVerify')->name('mail.verify.form');
                Route::post('mail/verify/submit/{token}','mailVerifySubmit')->name('mail.verify.form.submit');
            });

            Route::controller(PaymentController::class)->prefix('payment')->name('payment.')->group(function(){
                Route::get('preview/{token}','paymentPreview')->name('preview');
                Route::post('preview/submit/{token}','paymentConfirm')->name('preview.submit');
            });

        });

    });

});

// Bills API
Route::prefix('bills/api/v1')->controller(ApiBillPayController::class)->name('qrpay.bills.')->group(function(){
    Route::post('/airtime', 'buyAirtime')->name('airtime');
    Route::post('/data','buyData')->name('data');
    Route::post('/power','buyPower')->name('power');
    Route::post('/cable','buyCable')->name('cable');
    // Get plans and ID
    Route::get('/networks', 'networks')->name('networks');
    Route::get('/data/plans', 'data_plans')->name('data.plans');
    Route::get('/cables', 'cables')->name('cables');
    Route::get('/cable/plans', 'cable_plans')->name('cables.plans');
    Route::get('/powers', 'powers')->name('powers');


});
Route::get('success',function(Request $request) {
    dd($request->all());
});
