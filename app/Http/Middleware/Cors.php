<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $allowedOrigins = [
            'https://www.escolifesciences.com',
            'https://escoaster.com',
            'https://escovaccixcell.com',
            'http://www.escoglobal.com',
            'http://www.escolifesciences.cn',
            'http://au.escoglobal.com',
            'http://my.escoglobal.com',
            'http://www.escoglobal.com.ph',
            'http://za.escoglobal.com',
            'http://escolifesciences.co.id',
            'http://escolifesciences.co.th',
            'http://escolifesciences.pk',
            'http://vn.escoglobal.com',
            'https://www.escoglobal.es',
            'http://www.escoglobal.de',
            'https://escolifesciences.ru',
            'https://escolifesciences.it',
            'https://escolifesciences.eu',
            'http://escolifesciences.us',
            'https://www.escoglobal.co.uk',
            'http://escolifesciences.com.mm',
            'https://escolifesciences.co.kr',
            'http://escolifesciences.hk',
            'http://escolifesciences.tw',
            'https://www.escomedicalgroup.com',
            'http://www.escomedical.cn',
            'http://escomedical.cn',
            'https://esco-medical.com',
            'https://www.esco-medical.com',
            'http://escovirtualreality.com',
            'https://escovirtualreality.com',
            'https://escolifesciences.us',

            /* Test Websites */
            'https://test.esco-medical.com',

            'http://localhost:8000',
        ];

        if (in_array($request->header('Origin'), $allowedOrigins)) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $request->header('Origin'))
                ->header('Access-Control-Allow-Methods', 'POST');
        }

        return response('Unauthorized', 403);
    }
}
