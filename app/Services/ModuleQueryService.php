<?php

namespace App\Services;

use App\Models\Core\ModuleQuery;
use App\Models\Module\Module;
use App\Models\User;

class ModuleQueryService
{
    public function getUserModuleQueries(User $user, $moduleOrPermission, $idsOnly, Module $module)
    {
        $queries = [];

        if (preg_match('/[A-Za-z]*\.[A-Z-a-z]/', $moduleOrPermission)) {
            dd(true);
        } else {
            $permissionIds = $module->permissions->pluck('_id')->toArray();
        }

        if ($user instanceof User) {
            $user->load([
                'roles',
                'roles.filters',
            ]);

            $roles = $user->roles;

            foreach ($roles as $role) {
                $moduleQueryIds = $role->filters->whereIn('permission_id', $permissionIds)->pluck('module_query_ids')->flatten()->toArray();

                if (empty($moduleQueryIds)) {
                    $queries = [];

                    break;
                } else {
                    $queries = array_merge($queries, $moduleQueryIds);
                }
            }

            if (! $idsOnly) {
                return ModuleQuery::whereIn('_id', $queries)->get();
            }
        } else {
            $queries = $module->queries;

            if ($idsOnly) {
                $queries = $queries->pluck('_id')->toArray();
            }
        }

        return $queries;
    }
}
