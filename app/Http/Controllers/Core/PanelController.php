<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\PanelResource;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser();
    }

    public function getModulePanels(Request $request)
    {
        $this->moduleDataCollector->setModule($request->input('module-name'))->setPanels();

        $panels = $this->moduleDataCollector->panels;

        return PanelResource::customCollection($panels);
    }
}
