<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\FieldResource;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser();
    }

    public function getModuleFields(Request $request)
    {
        $this->moduleDataCollector->setModule($request->input('module-name'))->setFields();

        $fields = $this->moduleDataCollector->fields;

        $pickLists = $this->moduleDataCollector->pickLists;

        FieldResource::information($fields, $pickLists, null);

        return FieldResource::collection($fields);
    }
}
