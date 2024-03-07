<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Contact;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser()->setModule('contacts');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $contact = $this->moduleDataCollector->postStore($request);

        return $contact->_id;
    }

    public function show(Contact $contact, Request $request)
    {
        return $this->moduleDataCollector->getShow($contact, $request);
    }

    public function update(Contact $contact, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($contact, $request);
    }

    public function postMergeDuplicates(string $identifier, Request $request)
    {
        return $this->moduleDataCollector->postMergeDuplicates($identifier, $request);
    }
}
