<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Account;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('accounts');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $account = $this->moduleDataCollector->postStore($request);

        return $account->_id;
    }

    public function show(Account $account, Request $request)
    {
        return $this->moduleDataCollector->getShow($account, $request);
    }

    public function postMergeDuplicateAccounts(string $identifier, Request $request)
    {
        return $this->moduleDataCollector->postMergeDuplicate($identifier, $request);
    }
}
