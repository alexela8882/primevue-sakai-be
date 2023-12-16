<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomSaml2Controller extends Controller
{
    public function getTokenWithoutPassword($email) {
      return $email;
      // $client = DB::table('oauth_clients')
      //           ->where('password_client', true)
      //           ->get()[0];
      // $data = [
      //     'grant_type' => 'password',
      //     'client_id' => $client->id,
      //     'client_secret' => $client->secret,
      //     'username' => $req->username,
      //     'password' => 'what-is-your-password', // just leave whatever string
      //     'scope' => '',
      // ];
      // $response = Request::create(url('/oauth/token'), 'POST', $data);
      // return json_decode(app()->handle($response)->getContent());
    }
}
