<?php

use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\Company\CampaignController;
use App\Http\Controllers\Core\FieldController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\PanelController;
use App\Http\Controllers\Core\PicklistController;
use App\Http\Controllers\Core\ViewFilterController;
use App\Http\Controllers\Customer\AccountController;
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

Route::post('login', [RegisterController::class, 'login'])->name('login');
Route::post('saml-login', [RegisterController::class, 'samlLogin']);
Route::get('passwordless-login', [RegisterController::class, 'passwordLessLogin'])->middleware(['web']);
Route::get('logout', [RegisterController::class, 'logout'])->middleware(['web']);

Route::middleware('auth:api')->group(function () {
    Route::apiResource('modules/accounts', AccountController::class);

    Route::apiResource('countries', CountryController::class)->only('index');

    Route::get('/getModuleFields', [FieldController::class, 'getModuleFields']);

    Route::get('/getMenuNavigation', [FolderController::class, 'getMenuNavigation']);

    Route::apiResource('modules/leads', LeadController::class);

    Route::apiResource('modules', ModuleController::class);

    Route::apiResource('modules/salesopportunities', SalesOpportunityController::class);

    Route::get('/getModulePanels', [PanelController::class, 'getModulePanels']);

    Route::post('/picklist', [PicklistController::class, 'index']);

    Route::get('/user', [UserController::class, 'getUser']);

    Route::apiResource('viewFilters', ViewFilterController::class);

	Route::apiResource('campaigns', CampaignController::class);
});

// Route::controller(UserController::class)
//     ->prefix('users')
//     ->middleware('auth:api')
//     ->group(function () {
//         Route::get('{id}/get', 'get');
//         Route::get('/', 'all');
//         Route::post('/store', 'store');
//         Route::put('{id}/update', 'update');
//         Route::delete('{id}/delete', 'delete');
//     });

// Route::controller(UserConfigController::class)
//     ->prefix('user-configs')
//     ->middleware('auth:api')
//     ->group(function () {
//         Route::get('get-app-theme', 'getAppTheme');
//         Route::post('change-app-theme', 'changeAppTheme');
//     });

// // SAML2 Auth
// Route::controller(CustomSaml2Controller::class)
//     ->prefix('custom-saml2')
//     ->middleware(['web'])
//     ->group(function () {
//         Route::get('/logout', 'logout');
//     });
