<?php

use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Customer\LeadController;
use App\Http\Controllers\Customer\SalesOpportunityController;
use App\Http\Controllers\Folder\FolderController;
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

Route::get('modules/salesopportunities', [SalesOpportunityController::class, 'index']);

Route::post('register', [RegisterController::class, 'register']);
Route::post('login', [RegisterController::class, 'login'])->name('login');
Route::post('saml-login', [RegisterController::class, 'samlLogin']);
Route::get('passwordless-login', [RegisterController::class, 'passwordLessLogin'])->middleware(['web']);
Route::get('logout', [RegisterController::class, 'logout'])->middleware(['web']);

Route::middleware('auth:api')->group(function () {
    Route::apiResource('countries', CountryController::class)->only('index');

    Route::get('/getMenuNavigation', [FolderController::class, 'getMenuNavigation']);

    Route::apiResource('modules/leads', LeadController::class);

    Route::apiResource('modules', ModuleController::class);

    
});

