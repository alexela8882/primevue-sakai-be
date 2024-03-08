<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\Employee\Employee;
use App\Models\User;
use App\Services\ModuleDataCollector;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('employees');

        $this->user = auth('api')->user();
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $user = $this->moduleDataCollector->postStore($request);

        return $user?->_id;
    }

    public function show(Employee $user, Request $request)
    {
        return $this->moduleDataCollector->getShow($user, $request);
    }

    public function getUser(Request $request)
    {

        if ($request->headers->get('isApp')) {
            $user = auth('api')->user();

            $loginHistory = $user->loginHistories()->create([
                'device' => false,
                'browser' => false,
                'platform' => $request->headers->get('os') == 'android' ? 'Android OS' : 'iOS',
                'ip_address' => $request->server('HTTP_CF_CONNECTING_IP'),
                'isDesktop' => false,
                'isMobile' => false,
                'isApp' => true,
            ]);

            $user->update(['recent_login_at' => $loginHistory->created_at->format('Y-m-d H:i:s')]);
            $user->loginHistories()->latest('created_at')->skip(10)->get()->each(function ($loginHistory) {
                $loginHistory->delete();
            });
        }

        return UserResource::make(User::with(['branch', 'roles', 'handledBranches'])->find(auth()->user()->id));
    }

    public function deactivateUser($id)
    {
        $user = User::find($id);

        if ($user) {

            $user->active = false;
            $user->deactivated_at = Carbon::now();
            $user->save();

            $contact = $user->contactInformation();

            if ($contact) {
                $contact->update(['active' => false]);
            }

            return response()->json(['user' => $user, 'message' => 'User successfully deactivated.'], 200);
        } else {
            return response()->json(['message' => 'User not found.'], 200);
        }
    }
}
