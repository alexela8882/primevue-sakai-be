<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\FieldResource;
use App\Models\Core\Entity;
use App\Services\ModuleDataCollector;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FieldController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser();
    }

    public function getModuleFields(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity-name' => 'required|string|exists:entities,name',
        ]);

        if ($validator->fails()) {
            return $this->respondUnprocessable($validator->errors());
        }

        $entity = Entity::query()
            ->where('name', $request->input('entity-name'))
            ->with([
                'fields',
                'fields.fieldType',
                'fields.rules',
            ])
            ->first();

        if ($entity instanceof Entity) {
            $this->moduleDataCollector->entity = $entity;

            $this->moduleDataCollector->setFields();

            return FieldResource::customCollection($this->moduleDataCollector->fields, $this->moduleDataCollector->pickLists);
        } else {
            return $this->respondUnprocessable("Error. Unable to find instance of Entity with name of '{$request->input('entity-name')}'.");
        }

    }
}
