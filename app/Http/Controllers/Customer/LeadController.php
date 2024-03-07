<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Lead;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('leads');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $lead = $this->moduleDataCollector->postStore($request);

        return $lead?->_id;
    }

    public function show(Lead $lead, Request $request)
    {
        return $this->moduleDataCollector->getShow($lead, $request);
    }

    public function update(Lead $lead, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($lead, $request);
    }
}
