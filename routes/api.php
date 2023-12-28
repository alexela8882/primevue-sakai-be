<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserConfigController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\CustomSaml2Controller;

// use Daveismyname\MsGraph\Models\MsGraphToken;

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

Route::get('/phpinfo', function () {
  return phpinfo();
})->name('phpinfo');

Route::post('register', [RegisterController::class, 'register']);
Route::post('login', [RegisterController::class, 'login']);
Route::get('passwordless-login', [RegisterController::class, 'passwordLessLogin'])->middleware(['web']);
Route::get('logout', [RegisterController::class, 'logout'])->middleware(['web']);

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

// MS GRAPH
Route::group(['prefix' => 'msgraph', 'middleware' => ['web', 'saml2']], function() {
  Route::group(['middleware' => ['web', 'MsGraphAuthenticated']], function() {
    Route::get('/', function() {
      return MsGraph::get('me');
    });

    Route::group(['prefix' => '/mail-folders'], function () {
      Route::get('/', function() {
        return MsGraph::get('me/mailFolders');
      });
  
      Route::get('{id}', function($id) {
        return MsGraph::get('me/mailFolders/' . $id);
      });
  
      Route::get('{id}/messages', function($id) {
        return MsGraph::get('me/mailFolders/' . $id . '/messages');
      });
  
      Route::get('{id}/messages', function($id) {
        return MsGraph::get('me/mailFolders/' . $id . '/messages');
      });
  
      Route::get('{id}/messages/{messageId}', function($id, $messageId) {
        return MsGraph::get('me/mailFolders/' . $id . '/messages/' . $messageId);
      });
    });

    Route::get('/messages', function() {
      return MsGraph::get('me/messages');
    });

    Route::get('/messages/{id}', function($id) {
      return MsGraph::get('me/messages/' . $id . '=/?$select=subject,body,bodyPreview,uniqueBody');
    });
  });

  Route::get('oauth', function() {
    return MsGraph::connect();
  });
});
