<?php

use App\Http\Controllers\Account\UserController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserConfigController;
use App\Http\Controllers\Company\CampaignController;
use App\Http\Controllers\Core\FieldController;
use App\Http\Controllers\Core\LookupController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\PanelController;
use App\Http\Controllers\Core\PicklistController;
use App\Http\Controllers\Core\QuotationTemplateController;
use App\Http\Controllers\Core\ViewFilterController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\ContactController;
use App\Http\Controllers\Customer\LeadController;
use App\Http\Controllers\Customer\SalesOpportunityController;
use App\Http\Controllers\Customer\SalesQuotationController;
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

Route::controller(UserConfigController::class)
    ->prefix('user-configs')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('get-app-theme', 'getAppTheme');
        Route::post('change-app-theme', 'changeAppTheme');
    });

Route::post('login', [RegisterController::class, 'login'])->name('login');
Route::post('saml-login', [RegisterController::class, 'samlLogin']);
Route::get('passwordless-login', [RegisterController::class, 'passwordLessLogin'])->middleware(['web']);
Route::get('logout', [RegisterController::class, 'logout'])->middleware(['web']);

Route::middleware('auth:api')->group(function () {
    Route::patch('/modules/accounts/{account}/patchUpsert', [AccountController::class, 'patchUpsert']);
    Route::post('/modules/accounts/postMergeDuplicates/{identifier}', [AccountController::class, 'postMergeDuplicates']);

    Route::post('/modules/contacts/postMergeDuplicates/{identifier}', [ContactController::class, 'postMergeDuplicates']);

    Route::get('/getModuleFields', [FieldController::class, 'getModuleFields']);

    Route::get('/getMenuNavigation', [FolderController::class, 'getMenuNavigation']);

    Route::get('/getModulePanels', [PanelController::class, 'getModulePanels']);

    Route::post('/picklist', [PicklistController::class, 'getLists']);

    Route::get('/user', [UserController::class, 'getUser']);

    Route::patch('/user/deactivate/{id}', [UserController::class, 'deactivateUser']);

    Route::post('/lookup', [LookupController::class, 'getLookup']);

    Route::patch('/patchInlineUpdates', [ModuleController::class, 'patchInlineUpdates']);
    Route::get('/getShowRelatedList', [ModuleController::class, 'getShowRelatedList']);

    Route::patch('/modules/salesquotes/upsert/{id}', [SalesQuotationController::class, 'upsert']);

    Route::get('/modules/salesopportunities/getsjinfo/{id}', [SalesOpportunityController::class, 'getItemsWithSJ']);

    Route::get('/modules/salesopportunities/getactiveitems/{id}', [SalesOpportunityController::class, 'getActiveItems']);

    Route::get('/modules/salesopportunities/getAccountIds/{id}', [SalesOpportunityController::class, 'getAccountIds']);

    Route::post('/modules/salesopportunities/convert/{leadid}', [SalesOpportunityController::class, 'convert']);

    Route::post('/modules/salesopportunities/checkoppdetails/{id}', [SalesOpportunityController::class, 'checkDetails']);

    Route::post('/modules/salesopportunities/transfer/{id}', [SalesOpportunityController::class, 'transferOpportunity']);

    Route::patch('/modules/salesopportunities/upsert/{id}', [SalesOpportunityController::class, 'upsert']);

    Route::apiResources([
        'campaigns' => CampaignController::class,
        'modules/contacts' => ContactController::class,
        'countries' => CountryController::class,
        'modules/accounts' => AccountController::class,
        'modules/leads' => LeadController::class,
        'modules/salesopportunities' => SalesOpportunityController::class,
        'modules/salesquotes' => SalesQuotationController::class,
        'modules' => ModuleController::class,
        'viewFilters' => ViewFilterController::class,
        'quotationtemplates' => QuotationTemplateController::class,
        'users' => UserController::class,
    ]);
});
