<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    protected function unauthenticated($request, array $guards)
    {
        abort(Response::json([
            'message' => 'Error. Unauthenticated user. Kindly login first.',
        ], 401));
    }
}
