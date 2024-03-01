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
    Route::get('/getModuleFields', [FieldController::class, 'getModuleFields']);

    Route::get('/getMenuNavigation', [FolderController::class, 'getMenuNavigation']);

    Route::get('/getModulePanels', [PanelController::class, 'getModulePanels']);

    Route::post('/picklist', [PicklistController::class, 'getLists']);

    Route::get('/user', [UserController::class, 'getUser']);

    Route::apiResources([
        'campaigns' => CampaignController::class,
        'countries' => CountryController::class,
        'modules/accounts' => AccountController::class,
        'modules/leads' => LeadController::class,
        'modules/salesopportunities' => SalesOpportunityController::class,
        'modules' => ModuleController::class,
        'viewFilters' => ViewFilterController::class,
    ]);
});

Route::controller(UserConfigController::class)
    ->prefix('user-configs')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('get-app-theme', 'getAppTheme');
        Route::post('change-app-theme', 'changeAppTheme');
    });
