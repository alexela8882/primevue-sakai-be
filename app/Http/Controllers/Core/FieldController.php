<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\FieldRequest;
use App\Http\Resources\Core\FieldResource;
use App\Services\ModuleDataCollector;

class FieldController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser();
    }

    public function getModuleFields(FieldRequest $request)
    {
        $this->moduleDataCollector->setModule($request->input('module-name'))->setFields();

        $fields = $this->moduleDataCollector->fields;

        $pickLists = $this->moduleDataCollector->pickLists;

        return FieldResource::customCollection($fields, $pickLists);
    }
}
