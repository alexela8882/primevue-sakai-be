<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser()->setModule('contacts');
    }

    public function postMergeDuplicateContacts(string $identifier, Request $request)
    {
        return $this->moduleDataCollector->postMergeDuplicate($identifier, $request);
    }
}
