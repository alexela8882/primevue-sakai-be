<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomSaml2Controller extends Controller
{
    public function getTokenWithoutPassword($email)
    {
        return response()->json($email, 200);
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

    public function getAccessToken()
    {
        $authUrl = 'https://login.microsoftonline.com/25522f59-e20f-4a67-b76e-abcfd590301d/oauth2/authorize';
        $tokenUrl = 'https://login.microsoftonline.com/25522f59-e20f-4a67-b76e-abcfd590301d/oauth2/v2.0/token';
        $graphUrl = 'https://graph.microsoft.com/v1.0/users';

        // start guzzle
        $client = new \GuzzleHttp\Client();

        // token using client_credentials
        $tokenRes = $client->post(
            $tokenUrl,
            [
                'content-type' => 'application/x-www-form-urlencoded',
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    // 'grant_type' => 'authorization_code',
                    // 'code' => 'authorization_code',
                    'client_id' => '48ce6988-8bac-4ffc-8d98-c228057043c2',
                    'client_secret' => 'ZkM8Q~cY55o4AE8llsUgEIUlIcOSyPXGOCIfta0B',
                    // 'redirect_uri' => 'https://3069-2001-4452-2f4-2800-27ee-8b72-5402-21e0.ngrok-free.app/saml2/2b6cf328-c9fe-40cf-9ccf-a95dc20a1341/acs',
                    'scope' => 'https://graph.microsoft.com/.default',
                    // 'state' => 'random123',
                ],
            ]
        );

        // authorization_code
        // $authRes = $client->post(
        //   $authUrl,
        //   [
        //     'debug' => TRUE,
        //     'form_params' => [
        //       'response_type' => 'code',
        //       'client_id' => '48ce6988-8bac-4ffc-8d98-c228057043c2',
        //       'redirect_uri' => 'https://3069-2001-4452-2f4-2800-27ee-8b72-5402-21e0.ngrok-free.app/saml2/2b6cf328-c9fe-40cf-9ccf-a95dc20a1341/acs',
        //       'scope' => 'openid offline',
        //       'state' => 'umYJsmBJBbqa1ilW',
        //     ]
        //   ]
        // );

        // get response
        $data = json_decode($tokenRes->getBody()); // returns an object
        $access_token = $data->access_token;

        // for msgraph
        $client2 = new \GuzzleHttp\Client();
        $graph_response = $client2->get(
            $graphUrl,
            [
                'headers' => [
                    'content-type' => 'application/json',
                    'Authorization' => "Bearer {$access_token}",
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    // 'grant_type' => 'authorization_code',
                    // 'code' => 'authorization_code',
                    'client_id' => '48ce6988-8bac-4ffc-8d98-c228057043c2',
                    'client_secret' => 'ZkM8Q~cY55o4AE8llsUgEIUlIcOSyPXGOCIfta0B',
                    // 'redirect_uri' => 'https://3069-2001-4452-2f4-2800-27ee-8b72-5402-21e0.ngrok-free.app/saml2/2b6cf328-c9fe-40cf-9ccf-a95dc20a1341/acs',
                    'scope' => 'https://graph.microsoft.com/.default',
                    // 'state' => 'random123',
                ],
            ]
        );

        return $graph_response;
    }

    public function logout(Request $request)
    {
        // reset saml2 sessions
        // session(['xaccessToken' => null]);
        // session(['xuser_id' => null]);
        // session(['xuser_email' => null]);

        // forget saml2 sessions
        session()->forget('xaccessToken');
        session()->forget('xuser_id');
        session()->forget('xuser_email');

        return redirect()->route('phpinfo');
    }
}
