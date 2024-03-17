<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ModuleResource;
use App\Http\Resources\Folder\FolderResource;
use App\Models\Core\Field;
use App\Models\Core\Folder;
use App\Models\Module\Module;
use App\Models\User\Permission;
use App\Models\User\Role;
use App\Services\ModuleDataCollector;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser();
    }

    public function index()
    {
        $user = $this->moduleDataCollector->user;

        $user->load([
            'roles',
            'roles.permissions' => fn ($query) => $query->where('name', 'like', '%index%'),
            'roles.permissions.module',
            'roles.permissions.module.entity',
        ]);

        $modules = $user
            ->roles
            ->map(fn (Role $role) => $role->permissions)
            ->collapse()
            ->unique('name')
            ->map(fn (Permission $permission) => $permission->module);

        return $this->respondSuccessful("Successfully retrieved modules of {$user->fullName}.", ModuleResource::collection($modules));
    }

    public function getShowRelatedList(Request $request)
    {
        return $this->moduleDataCollector
            ->setModule($request->input('module-name'))
            ->getShowRelatedList($request);
    }

    public function patchInlineUpdates(Request $request)
    {
        $collections = collect($request->input('items'));

        if ($request->filled('module-name')) {
            if ($collections->isNotEmpty()) {
                $this->moduleDataCollector->setModule($request->input('module-name'));

                $collectionsFields = $collections->collapse()->keys()->filter(fn ($value) => $value !== '_id');

                $fields = $this->moduleDataCollector->entity->fields;

                $filteredFields = $fields->filter(function (Field $field) {
                    if ($field->entity->name === 'SalesOpportunity' && in_array($field->name, ['currency_id', 'pricebook_id'])) {
                        return false;
                    } elseif ($field->name == 'owner_id') {
                        return false;
                    } elseif (in_array($field->fieldType->name, ['autonumber', 'readOnly'])) {
                        return false;
                    }

                    return true;
                });

                $exceptionFields = $collectionsFields->diff($filteredFields->pluck('name'));

                if ($exceptionFields->isEmpty()) {
                    $this->moduleDataCollector->entity->getModel()
                        ->whereIn('_id', $collections->pluck('_id'))
                        ->select($collectionsFields)
                        ->each(fn ($model) => $model->update($collections->firstWhere('_id', $model->_id)));

                    return $this->respondSuccessful('Inline updated successfully.');
                } else {
                    return $this->respondUnprocessable("Error. The following fields are not allowed to be inline updated: {$fields->whereIn('name', $exceptionFields)->implode('label', ', ')}");
                }
            } else {
                return $this->respondUnprocessable('Error. There is no provided items to be inline updated.');
            }
        } else {
            return $this->respondUnprocessable('Error. Module name is missing.');
        }
    }

    public function getMenu()
    {
        $folder = Folder::query()
            ->where('name', 'top')
            ->where('type_id', '5bb104cf678f71061f643c27') // Folder Type: Module Navigation | 5bb104cf678f71061f643c27
            ->first();

        return FolderResource::make($folder);
    }
}
