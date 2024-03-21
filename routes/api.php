<?php

use App\Http\Controllers\Account\UserController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserConfigController;
use App\Http\Controllers\Company\CampaignController;
use App\Http\Controllers\Core\FieldController;
use App\Http\Controllers\Core\LogController;
use App\Http\Controllers\Core\LookupController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\PanelController;
use App\Http\Controllers\Core\PDFController;
use App\Http\Controllers\Core\PickListController;
use App\Http\Controllers\Core\QuotationTemplateController;
use App\Http\Controllers\Core\ViewFilterController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\ContactController;
use App\Http\Controllers\Customer\LeadController;
use App\Http\Controllers\Customer\SalesOpportunityController;
use App\Http\Controllers\Customer\SalesQuotationController;
use App\Http\Controllers\Product\PricebookController;
use App\Http\Controllers\Report\ReportController;
// STATIC
use App\Http\Controllers\Static\ActivityLogController;
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

Route::middleware('cors')->group(function () {
    Route::post('/rfq/lifesciences', [LeadController::class, 'storeLifesciencesRFQ']);
    Route::post('/rfq/medical', [LeadController::class, 'storeMedicalRFQ']);
    Route::post('/rfq/vaccixcell', [LeadController::class, 'storeVaccixcellRFQ']);
});

Route::middleware('auth:api')->group(function () {
    // When putting a non api-resource route,
    // please it alphabetically via controller's name

    Route::patch('/modules/accounts/{account}/patchUpsert', [AccountController::class, 'patchUpsert']);
    Route::post('/modules/accounts/postMergeDuplicates/{identifier}', [AccountController::class, 'postMergeDuplicates']);

    Route::post('/modules/contacts/postMergeDuplicates/{identifier}', [ContactController::class, 'postMergeDuplicates']);

    Route::get('/getModuleFields', [FieldController::class, 'getModuleFields']);

    Route::get('/getModulePanels', [PanelController::class, 'getModulePanels']);

    Route::get('/modules/menu', [ModuleController::class, 'getMenu']);

    Route::post('/picklist', [PickListController::class, 'getLists']);

    Route::get('/user', [UserController::class, 'getUser']);

    Route::patch('/user/deactivate/{id}', [UserController::class, 'deactivateUser']);

    Route::get('/logs', [LogController::class, 'index']);

    Route::post('/lookup', [LookupController::class, 'getLookup']);
    Route::post('/lookup/item', [LookupController::class, 'getLookupItem']);

    Route::patch('/patchInlineUpdates', [ModuleController::class, 'patchInlineUpdates']);
    Route::get('/getShowRelatedList', [ModuleController::class, 'getShowRelatedList']);

    Route::patch('/modules/pricebooks/{pricebook}/patchAddPricelist', [PricebookController::class, 'patchAddPricelist']);
    Route::patch('/modules/pricebooks/{pricebook}/patchAddFormula', [PricebookController::class, 'patchAddFormula']);
    Route::post('/modules/pricebooks/{pricebook}/postComputePrice', [PricebookController::class, 'postComputePrice']);
    Route::post('/modules/pricebooks/{pricebook}/postApplyComputePrice', [PricebookController::class, 'postApplyComputePrice']);
    Route::post('/modules/pricebooks/{pricebook}/postCancelComputePrice', [PricebookController::class, 'postCancelComputePrice']);

    Route::patch('/modules/salesquotes/upsert/{id}', [SalesQuotationController::class, 'upsert']);

    Route::get('/modules/salesopportunities/getsjinfo/{id}', [SalesOpportunityController::class, 'getItemsWithSJ']);

    Route::get('/modules/salesopportunities/getactiveitems/{id}', [SalesOpportunityController::class, 'getActiveItems']);

    Route::get('/modules/salesopportunities/getAccountIds/{id}', [SalesOpportunityController::class, 'getAccountIds']);

    Route::post('/modules/salesopportunities/convert/{leadid}', [SalesOpportunityController::class, 'convert']);

    Route::post('/modules/salesopportunities/checkoppdetails/{id}', [SalesOpportunityController::class, 'checkDetails']);

    Route::post('/modules/salesopportunities/transfer/{id}', [SalesOpportunityController::class, 'transferOpportunity']);

    Route::patch('/modules/salesopportunities/upsert/{id}', [SalesOpportunityController::class, 'upsert']);

    Route::get('/modules/reports/gettypes', [ReportController::class, 'getTypes']);

    Route::get('/modules/folders/showreports/{id}', [ReportController::class, 'showReports']);

    Route::get('/quotationtemplates/getInfo/{id}', [QuotationTemplateController::class, 'getInfo']);

    Route::get('/download-pdf/{filename}', [PDFController::class, 'download']);

    Route::get('/downloadpdf/{filename}', [PDFController::class, 'generalDownload']);

    Route::get('/downloadfile/{id}', [PDFController::class, 'redownload']);

    Route::post('/generatepdf', [PDFController::class, 'pdfviewgeneral']);

    Route::post('/generate-pdf', [PDFController::class, 'PDFView']);

    Route::post('/deleteQuotePDF/{id}', [PDFController::class, 'deleteQuotePDF']);

    Route::get('/activity-logs/by-record/{record_id}', [ActivityLogController::class, 'indexByRecord']);

    Route::apiResources([
        'modules/accounts' => AccountController::class,
        'modules/contacts' => ContactController::class,
        'modules/leads' => LeadController::class,
        'modules/pricebooks' => PricebookController::class,
        'modules/salesopportunities' => SalesOpportunityController::class,
        'modules/salesquotes' => SalesQuotationController::class,
        'modules/reports' => ReportController::class,
        'modules/users' => UserController::class,
        'modules' => ModuleController::class,
        'campaigns' => CampaignController::class,
        'countries' => CountryController::class,
        'viewFilters' => ViewFilterController::class,
        'quotationtemplates' => QuotationTemplateController::class,
        'activity-logs' => ActivityLogController::class,
    ]);
});
