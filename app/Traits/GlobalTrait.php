<?php

namespace App\Traits;

use GuzzleHttp\Client;

trait GlobalTrait
{
    public function verifyCaptcha($request)
    {
        if ($request->has('g-recaptcha-response')) {
            $client = new Client();
            $secret = '6LeQpPMaAAAAAEFb20hAK7D8ol1ieR1L8FSyCKaq';
            $response = $request['g-recaptcha-response'];
            $ip = $request->ip();
            $response = $client->post('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$response.'&remoteip='.$ip);
            $data = json_decode($response->getBody(), true);

            logDrf($data, 'recaptcha-response');

            if ($data['success'] === true && $data['score'] >= 0.5) {
                return 1;
            }

            return 2;
        } elseif ($request->has('mass-update')) {
            return 1;
        }

        return 0;
    }

    public function stripAllTags($request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            if (in_array($key, ['mutables', 'photos'])) {
                $data[$key] = $value;
            } elseif (is_array($value)) {
                $data[$key] = array_map(function ($val) {
                    return trim(strip_tags($val));
                }, $data[$key]);
            } else {
                $data[$key] = trim(strip_tags($value));
            }
        }

        return $data;
    }
}
