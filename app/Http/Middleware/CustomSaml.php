<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomSaml
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('xaccessToken')) {
            // $token = $request->session()->get('xaccessToken');
            // return response()->json($token);
            return $next($request);
        } else {
            $res = 'not authorized';

            return redirect()->route('saml.login', ['uuid' => config('saml2.uuid')]);
        }

        return $next($request);
    }
}
