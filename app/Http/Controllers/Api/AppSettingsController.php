<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\AppOnboardScreens;
use App\Models\Admin\AppSettings;
use App\Models\Admin\BasicSettings;
use Exception;


class AppSettingsController extends Controller
{
    public function appSettings(){
        $splash_screen = AppSettings::get()->map(function($splash_screen){
            return[
                'id' => $splash_screen->id,
                'splash_screen_image' => $splash_screen->splash_screen_image,
                'version' => $splash_screen->version,
                'created_at' => $splash_screen->created_at,
                'updated_at' => $splash_screen->updated_at,
            ];
        })->first();
        $app_url = AppSettings::get()->map(function($url){
            return[
                'id' => $url->id,
                'android_url' => $url->android_url,
                'iso_url' => $url->iso_url,
                'created_at' => $url->created_at,
                'updated_at' => $url->updated_at,
            ];
        })->first();
        $onboard_screen = AppOnboardScreens::orderByDesc('id')->where('status',1)->get()->map(function($data){
            return[
                'id' => $data->id,
                'title' => $data->title,
                'sub_title' => $data->sub_title,
                'image' => $data->image,
                'status' => $data->status,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];

        });
        $basic_settings = BasicSettings::first();
        $all_logo = [
            "site_logo_dark" =>  @$basic_settings->site_logo_dark,
            "site_logo" =>  @$basic_settings->site_logo,
            "site_fav_dark" =>  @$basic_settings->site_fav_dark,
            "site_fav" =>  @$basic_settings->site_fav,
        ];
        $data =[
            "default_image"          => "public/backend/images/default/default.webp",
            "image_path"            =>  "public/backend/images/app",
            'onboard_screen'        => $onboard_screen,
            'splash_screen'         => (object)$splash_screen,
            'app_url'               =>   (object)$app_url,
            'all_logo'              =>   (object)$all_logo,
            "logo_image_path"       => "public/backend/images/web-settings/image-assets"

        ];
        $message =  ['success'=>[__("Data Fetch Successful")]];
        return Helpers::success($data,$message);

    }
    public function languages()
    {
        try{
            $api_languages = get_api_languages();
        }catch(Exception $e) {
            $error = ['error'=>[$e->getMessage()]];
            return Helpers::error($error);
        }
        $data =[
            'languages' => $api_languages,
        ];
        $message =  ['success'=>[__('Language Data Fetch Successfully!')]];
        return Helpers::success($data,$message);
    }
}
