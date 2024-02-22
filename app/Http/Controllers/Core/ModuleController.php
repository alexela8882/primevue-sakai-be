<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\ModuleService;

class ModuleController extends Controller
{
    public function __construct(private ModuleService $moduleService)
    {
        //
    }

    public function index()
    {
        return $this->moduleService->getModules();
    }
}
