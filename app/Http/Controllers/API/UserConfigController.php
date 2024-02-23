<?php

namespace App\Http\Controllers\API;

use App\Models\UserConfig;
use Illuminate\Http\Request;

class UserConfigController extends BaseController
{
    public function getAppTheme(Request $req)
    {
        $userConfig = UserConfig::where('user_id', $req->user()->id)
            ->first();

        return response()->json($userConfig, 200);
    }

    public function changeAppTheme(Request $req)
    {
        $userConfig = UserConfig::where('user_id', $req->user()->id)
            ->first();

        if ($userConfig) {
            if ($req->app_theme) {
                $userConfig->app_theme = $req->app_theme;
            }
            if ($req->app_theme_dark) {
                $userConfig->app_theme_dark = $req->app_theme_dark;
            }
            if ($req->app_theme_scale) {
                $userConfig->app_theme_scale = $req->app_theme_scale > 14 ? '14' : $req->app_theme_scale;
            }
            if ($req->app_theme_ripple) {
                $userConfig->app_theme_ripple = $req->app_theme_ripple;
            }
            if ($req->app_theme_menu_type) {
                $userConfig->app_theme_menu_type = $req->app_theme_menu_type;
            }
            if ($req->app_theme_input_style) {
                $userConfig->app_theme_input_style = $req->app_theme_input_style;
            }
            $userConfig->update();
        } else {
            $newUserConfig = new UserConfig;
            $newUserConfig->user_id = $req->user()->id;
            if ($req->app_theme) {
                $newUserConfig->app_theme = $req->app_theme;
            }
            if ($req->app_theme_dark) {
                $newUserConfig->app_theme_dark = $req->app_theme_dark;
            }
            if ($req->app_theme_scale) {
                $newUserConfig->app_theme_scale = $req->app_theme_scale > 14 ? '14' : $req->app_theme_scale;
            }
            if ($req->app_theme_ripple) {
                $newUserConfig->app_theme_ripple = $req->app_theme_ripple;
            }
            if ($req->app_theme_menu_type) {
                $newUserConfig->app_theme_menu_type = $req->app_theme_menu_type;
            }
            if ($req->app_theme_input_style) {
                $newUserConfig->app_theme_input_style = $req->app_theme_input_style;
            }
            $newUserConfig->save();
        }

        $response = [
            'message' => 'Theme applied',
        ];

        return response()->json($response, 200);
    }
}
