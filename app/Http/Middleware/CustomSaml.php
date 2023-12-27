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
          return $next($request);
        } else {
          $res = "not authorized";
          return redirect()->route('saml.login', ['uuid' => 'cd4d3b47-576c-4df6-9fa0-09a3dffe9f27']);
        }

        return $next($request);
    }
}
