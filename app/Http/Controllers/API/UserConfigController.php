<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;

use App\Models\UserConfig;

class UserConfigController extends BaseController
{

    public function getAppTheme (Request $req) {
      $userConfig = UserConfig::where('user_id', $req->user()->id)
                    ->first();

      return response()->json($userConfig, 200);
    }

    public function changeAppTheme (Request $req) {
      $userConfig = UserConfig::where('user_id', $req->user()->id)
                    ->first();

      if ($userConfig && $userConfig->app_theme) {
        $userConfig->app_theme = $req->app_theme;
        $userConfig->update();
      } else {
        $newUserConfig = new UserConfig;
        $newUserConfig->user_id = $req->user()->id;
        $newUserConfig->app_theme = $req->app_theme;
        $newUserConfig->save();
      }

      $response = [
        'message' => 'Theme applied'
      ];

      return response()->json($response, 200);
    }
}
