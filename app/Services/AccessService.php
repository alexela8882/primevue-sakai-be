<?php

namespace App\Services;

use App\Models\Company\Branch;
use App\Models\Core\Module;
use App\Models\Core\ModuleQuery;
use App\Models\User;
use App\Models\User\Permission;
use App\Models\User\Role;
use Illuminate\Support\Collection;

class AccessService
{
    public function __construct()
    {

    }

    /**
     * @param  Repository  $repository
     * @return bool
     */
    public function match($userId, $role, &$repository)
    {
        $user = User::find($userId);

        // check if user has the role
        if ($user->hasRole($role->name)) {
            //    $ids = $this->getUserAccessByType($user, $role, $accessType, true);

            $accesses = $role->accesses()->where('user_id', $userId)->all();

            // if user's role is not restricted by any particular access type, don't apply any criteria
            if (! count($accesses)) {
                return true;
            }

            foreach ($accesses as $access) {

                $ids = $access->{$access->type.'_ids'};

                // if user's role is not restricted by any particular access type, don't apply any criteria
                if ($ids == null || is_array($ids) && ! count($ids)) {
                    continue;
                }

                // Add access type of roles with respective criteria here....
                switch (strtolower($access->type)) {
                    case 'branch':
                        $repository->pushCriteria(new UnderBranches($ids));
                        break;
                    case 'businessUnit':
                        $repository->pushCriteria(new UnderBranches($ids));
                        break;
                }

                return true;
            }

            return false;
        }

        return false;
    }

    // Check permission's access type
    public function check($userId, $permissionName, &$repository)
    {
        $roles = $this->getPermissionRoles($permissionName);

        foreach ($roles as $role) {
            $this->match($userId, $role, $repository);
        }
    }

    public function getUserModuleQueries($userId, $moduleOrPermission, $idsOnly = true)
    {
        $queries = [];
        if (preg_match('/[A-Za-z]*\.[A-Z-a-z]/', $moduleOrPermission)) {
            $permission = Permission::where(['name' => $moduleOrPermission]);
            $permissionIds = (array) $permission->_id;
            $module = $permission->module;
        } else {
            $module = Module::where(['name' => $moduleOrPermission]);
            $permissionIds = $module->permissions()->pluck('_id')->toArray();
        }

        if ($userId) {
            $roles = User::find($userId)->roles;

            foreach ($roles as $role) {
                $i = $role->roleFilters()->whereIn('permission_id', $permissionIds)->pluck('module_query_ids')->flatten()->toArray();
                if (! count($i)) {
                    $queries = [];
                    break;
                } else {
                    $queries = array_merge($queries, $i);
                }
            }

            if (! $idsOnly) {
                return ModuleQuery::whereIn('_id', $queries)->get();
            }
        } else {
            $queries = $module->queries;
            if ($idsOnly) {
                $queries->pluck('_id')->toArray();
            }
        }

        return $queries;
    }

    public function getUserModule()
    {
    }

    public function getPermissionRoles($permissionName)
    {
        $permission = Permission::where(['name' => $permissionName]);

        return $permission->roles;
    }

    public function getRoleModules($roleName)
    {
        if (is_object($roleName)) {
            $role = $roleName;
        } else {
            $role = Role::where(['name' => $roleName]);
        }
        $permissionIds = $role->perms()->pluck(['_id'])->toArray();

        return Module::whereIn('permission_id', $permissionIds);
    }

    public function hasUnder($roleIds, $branchId)
    {
        if ($roleIds) {
            $roles = [];
            $b = (array) $branchId;
            foreach ((array) $roleIds as $id) {
                $role = Role::find($id);

                if ($role) {
                    $roles = array_merge($role->underRole()->pluck('user_id')->flatten()->filter()->toArray(), $roles);
                }
            }

            $roles = array_values(array_unique($roles));

            $roles = User::whereIn('_id', $roles)->whereIn('handled_branch_ids', $b)->pluck('_id')->toArray();

            return $roles;
        }
    }

    public function getModuleRoles($moduleName, $idsOnly = false)
    {
        if (is_object($moduleName)) {
            $module = $moduleName;
        } elseif (is_array($moduleName)) {
            $moduleIds = Module::whereIn('name', $moduleName)->pluck(['_id'])->toArray();
        } else {
            $module = Module::where(['name' => $moduleName]);
        }

        if (is_array($moduleName)) {
            $permissionIds = Permission::whereIn('module_id', $moduleIds)->pluck('_id')->toArray();
        } else {
            $permissionIds = $module->permissions->pluck('_id')->toArray();
        }

        $roles = Role::whereIn('permission_id', $permissionIds);

        if ($idsOnly) {
            return $roles->pluck('_id');
        } else {
            return $roles;
        }
    }

    public function getPermissionUsers($permission, $field = '_id', $idsOnly = true)
    {

        if (is_array($permission) || $permission instanceof Collection && $permission = $permission->toArray()) {

            //            $permissions = Permission::getModel();
        } elseif ($modelClass = Permission::getModelClass()) {
        }
    }

    public function getRoleUsersWithinBranches($role, $branch, $rField = '_id', $bField = 'name', $idsOnly = true, $fields = ['*'])
    {
        if ($fields != ['*']) {
            $fields[] = 'handled_branch_ids';
        }
        $users = $this->getRoleUsers($role, $rField, false, $fields);
        $branchIds = Branch::whereIn($bField, (array) $branch)->pluck('_id')->toArray();
        $users = $users->filter(function ($user) use ($branchIds) {
            return count(array_intersect((array) $user->handled_branch_ids, $branchIds));
        });
        if ($idsOnly) {
            return $users->pluck('_id')->toArray();
        } else {
            return $users;
        }
    }

    public function getRoleUsers($role, $field = '_id', $idsOnly = true, $fields = ['*'])
    {

        $modelClass = Role::class;

        if (is_array($role) || $role instanceof Collection && $role = $role->toArray()) {
            $roles = Role::getModel()->whereIn($field, $role)->get();
            $users = new Collection();

            foreach ($roles as $role) {
                $roleUsers = $role->users()->get($fields);
                foreach ($roleUsers as $user) {
                    if (! $users->contains($user->_id)) {
                        $users->push($user);
                    }
                }
            }
        } elseif ($role instanceof $modelClass) {
            $users = $role->users()->get($fields);
        } else {
            $users = Role::where([$field => $role])->users()->get($fields);
        }

        if ($idsOnly) {
            return $users->pluck(['_id'])->toArray();
        } else {
            return $users;
        }
    }

    public function getHandledUsers($userId, $withSelf = true)
    {
        $user = User::find($userId);
        $branches = $user->handledBranches;
        $users = collect([]);
        if ($withSelf) {
            $users->push($user);
        }
        if ($branches->isNotEmpty()) {
            foreach ($branches as $branch) {
                $users = $users->merge($branch->users);
            }
        }

        return $users->unique();
    }

    public function getTechSupport()
    {
        return User::getModel()->whereIn('role_id', ['5c906a1ca6ebc7193110f9bb'])->project(['_id' => 1])->get()->pluck('_id')->toArray();
    }
}
