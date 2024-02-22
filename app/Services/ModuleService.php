<?php

namespace App\Services;

use App\Http\Resources\Core\ModuleCollection;
use App\Models\Core\Folder;
use App\Models\Core\Module;

class ModuleService
{
    public function getModules()
    {
        $topFolder = Folder::query()
            ->whereName('top')
            ->whereTypeId('5bb104cf678f71061f643c27')
            ->with([
                'modules' => fn (Module $module) => $module->setup != true,
            ])
            ->first();

        $modules = $topFolder->modules;

        return ModuleCollection::collection($modules);
    }
}
