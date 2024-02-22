<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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
        return $this->moduleDataCollector->data($request);
    }
}
