<?php

use Illuminate\Http\Request;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserConfigController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\CustomSaml2Controller;
use App\Http\Controllers\API\PicklistController;
use App\Http\Controllers\API\ViewFilterController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Customer\LeadController;
use App\Http\Controllers\Customer\SalesOpportunityController;
use Illuminate\Support\Facades\Route;

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
Route::post('login', [RegisterController::class, 'login'])->name('login');
Route::post('saml-login', [RegisterController::class, 'samlLogin']);
Route::get('passwordless-login', [RegisterController::class, 'passwordLessLogin'])->middleware(['web']);
Route::get('logout', [RegisterController::class, 'logout'])->middleware(['web']);

// Route::middleware('auth:api')->get('/user', [UserController::class,'getUser']);

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

    
Route::controller(LeadController::class)
    ->middleware('auth:api')
    ->group(function() {
        Route::apiResource('leads', LeadController::class);
    });

Route::controller(SalesOpportunityController::class)
        ->middleware('auth:api')
        ->group(function () {
            Route::apiResource('sales/opportunities', SalesOpportunityController::class);
        });

Route::controller(UserConfigController::class)
    ->prefix('user-configs')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('get-app-theme', 'getAppTheme');
        Route::post('change-app-theme', 'changeAppTheme');
    });

Route::controller(CountryController::class)
    ->prefix('countries')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', 'all');
    });

Route::controller(BranchController::class)
    ->prefix('branches')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', 'all');
        Route::post('/store', 'store');
        Route::put('{id}/update', 'update');
        Route::delete('{id}/delete', 'delete');
    });

// SAML2 Auth
Route::controller(CustomSaml2Controller::class)
    ->prefix('custom-saml2')
    ->middleware(['web'])
    ->group(function () {
        Route::get('/logout', 'logout');
    });

Route::controller(ModuleController::class)
    ->prefix('modules')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', 'all');
        Route::post('/store', 'store');
        Route::put('{id}/update', 'update');
        Route::delete('{id}/delete', 'delete');
    });


Route::controller(PicklistController::class)
    ->prefix('picklists')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', 'all');
        Route::post('/store', 'store');
        Route::put('{id}/update', 'update');
        Route::delete('{id}/delete', 'delete');
    });

Route::controller(ViewFilterController::class)
    ->prefix('viewFilters')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', 'all');
        Route::post('/store', 'store');
        Route::put('{id}/update', 'update');
        Route::delete('{id}/delete', 'delete');
    });

	