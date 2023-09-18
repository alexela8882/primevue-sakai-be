<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserConfigController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('register', [RegisterController::class, 'register']);
Route::post('login', [RegisterController::class, 'login']);

Route::middleware('auth:api')->get('/user', function (Request $request) {
  return $request->user();
});

Route::controller(UserController::class)
  ->prefix('users')
  ->middleware('auth:api')
  ->group(function () {
  Route::get('{id}/get', 'get');
  Route::get('/', 'all');
  Route::post('/store', 'store');
  Route::put('{id}/update', 'update');
  Route::delete('{id}/delete', 'delete');
});

Route::controller(UserConfigController::class)
  ->prefix('user-configs')
  ->middleware('auth:api')
  ->group(function () {
  Route::get('get-app-theme', 'getAppTheme');
  Route::post('change-app-theme', 'changeAppTheme');
});
