<?php

namespace App\Builders;

use App\Models\Core\Entity;
use App\Models\Core\Folder;
use App\Models\Core\ModuleQuery;
use App\Models\Module\Module;
use App\Models\User\Permission;

class ModuleBuilder
{
    protected $curModule;

    protected $curModulePermissions;

    protected $curModuleEntity;

    protected $curQueries;

    protected $decorated;

    protected $modules;

    protected $queries;

    protected $moduleIds;

    protected $permissions;

    protected $entities;

    protected $moduleFolderId;

    protected $allEntities;

    protected $queryBuilder;

    protected $user;

    protected $folderOrders;

    protected $folders;

    protected static $colors = [
        'rgba(63, 81, 181, 0.7)',
        'rgba(0, 150, 136, 0.7)',
        'rgba(244, 67, 54, 0.7)',
        'rgba(156, 39, 176, 0.7)',
        'rgba(76, 175, 80, 0.7)',
        'rgba(255, 193, 7, 0.7)',
        'rgba(103, 58, 183, 0.7)',
    ];

    protected static $defaultPermissionMsgs = [
        'index' => 'View all',
        'create' => 'Create',
        'show' => 'View',
        'delete' => 'Delete',
        'update' => 'Update',
    ];

    protected static $queryPatterns = [
        'owned' => ['where("Entity::owner_id", "=", "currentUser::_id")', 'My Entities'],
        'owned_and_under' => ['where("Entity::owner_id", "in", "currentUser::people")', 'All Owned Entities With Entities of Handled Employees'],
        'business_unit' => ['where("Entity::business_unit_ids", "=", "currentUser::business_unit_ids")', 'All Handled Entities'],
        'assigned' => ['where("Entity::assignee_ids", "=", "currentUser::_id")', 'All Assigned Entities'],
        'handled_branches' => ['where("Entity::branch_ids", "=", "currentUser::handledBranches")', ''],
    ];

    public function __construct()
    {
        $this->moduleFolderId = picklist_id('folder_types', 'module_nav');
        $this->reset();
    }

    public function deleteAllModulesAndPermissions()
    {
        Folder::where('type_id', $this->moduleFolderId)->forceDelete();
        Module::deleteAll();
        ModuleQuery::deleteAll();
        Permission::deleteAll();

        return $this;
    }

    public function defineSetup($name, $label, $link, $description = '', $labelAsMain = null)
    {

        $this->define($name, $label, $link, $description, $labelAsMain);
        $this->curModule['setup'] = true;

        return $this;
    }

    public function define($name, $label, $link, $description = '', $labelAsMain = null)
    {

        if ($this->curModule) {
            $this->storeCurrent();
        }

        $this->curModule = [
            'name' => $name,
            'label' => $label,
            'link' => $link,
            'description' => $description,
        ];
        if ($labelAsMain) {
            $this->curModule['label_as_main'] = $labelAsMain;
        }

        $this->curModulePermissions = collect([]);

        return $this;
    }

    public function addFolder($name, $label, $icon = null, $parentFolder = 'top', $order = null)
    {

        if ($this->curModule) {
            $this->storeCurrent();
        }

        if ($parentFolder) {
            $pfolder = Folder::where('name', $parentFolder)->first();
            if (! $pfolder) {
                throw new \Exception('Error. Folder "'.$parentFolder.'" is unknown');
            } else {
                $parentFolder = $pfolder->_id;
            }
        }

        $pfolder = Folder::where('name', $name)->first();
        if ($pfolder) {
            throw new \Exception('Error. Folder "'.$name.'" already exists.');
        }

        $folder = Folder::create([
            'name' => $name,
            'type_id' => $this->moduleFolderId,
            'label' => $label,
            'icon' => $icon,
            'order' => $this->getFolderOrder($pfolder->name, $order),
            'folder_id' => $parentFolder,
        ]);

        Folder::push($folder);

        return $this;
    }

    public function underFolder($folderName, $used = false)
    {

        $folder = Folder::where('name', $folderName)->first();
        if (! $folder) {
            throw new \Exception('Error. Folder '.$folderName.' does not exist');
        }

        if (! $used) {
            $this->curModule['order'] = $this->getFolderOrder($folderName);
        } else {
            $this->curModule['order'] = ($folder->modules()->count()) + 1;
        }

        $this->curModule['folder_id'] = $folder->_id;

        return $this;
    }

    public function merge($folder, array $modules)
    {

        $folder = Folder::where('name', $folder)->first();

        if (array_depth($modules) == 1) {
            $modules = [$modules];
        }

        foreach ($modules as $moduleGroup) {
            foreach ($moduleGroup as $moduleName) {
                $module = $this->modules->where('name', $moduleName)->first();
                if (! $module) {
                    throw new \Exception('Error. Unknown module '.$moduleName);
                }
                if ($module['folder_id'] != $folder->_id) {
                    throw new \Exception('Cannot merge module '.$module.' within folder '.$folder->name);
                }
            }
        }

        $folder->update(['merged' => $modules]);

        return $this;
    }

    protected function getFolderOrder($folderName, $order = null)
    {
        if ($order) {
            return $order;
        } else {
            $order = $this->folderOrders->where('name', $folderName)->first();
            if (! $order) {
                $this->folderOrders->push(makeObject(['name' => $folderName, 'cnt' => 1]));

                return 0;
            }

            return ++$order->cnt;
        }
    }

    public function asSubmoduleOf($moduleName)
    {
        $this->curModule['parent_module'] = $moduleName;

        return $this;
    }

    public function decorate($icon, $color = null)
    {
        $this->curModule['icon'] = $icon;
        $this->curModule['color'] = $color ?: static::$colors[array_rand(static::$colors)];
        $this->decorated = true;

        return $this;
    }

    public function hasViewFilter($hasVf = true)
    {
        $this->curModule['hasViewFilter'] = $hasVf;

        return $this;
    }

    /**
     * @param  array|string  $permissions
     * @return $this
     *
     * @throws \Exception
     */
    public function addPermissions($permissions = 'all')
    {
        if ($permissions == 'all') {
            $permissions = array_keys(static::$defaultPermissionMsgs);
        } elseif (is_string($permissions)) {
            $permissions = [$permissions];
        }

        if (array_depth($permissions) == 1) {

            $article = (starts_with_vowel($this->curModule['name']) ? ' an ' : ' a ');

            foreach ($permissions as $key => $permission) {
                if (is_string($key)) {
                    $namePostfix = $key;
                    $description = $permission;
                } else {
                    $namePostfix = $permission;
                    $description = '';
                    if (array_key_exists($permission, static::$defaultPermissionMsgs)) {
                        $description = static::$defaultPermissionMsgs[$permission];
                        if ($permission == 'index') {
                            $description .= ' '.str_plural($this->curModule['name']);
                        } else {
                            $description .= $article.str_singular($this->curModule['name']);
                        }
                    }
                }
                $label = static::$defaultPermissionMsgs[$namePostfix] ?? title_case($namePostfix);
                $this->permits($this->curModule['name'].'.'.$namePostfix, $label, $description);
            }
        } else {
            foreach ($permissions as $permission) {
                if (count($permission) < 2) {
                    throw new \Exception('Missing parameter for permission');
                }
                $this->permits($permission[0], $permission[1], $permission[2] ?? '');
            }
        }

        return $this;
    }

    public function addModuleElementPerms()
    {
        $this->curModule['hasModuleElements'] = true;
        $this->addPermissions([
            $this->curModule['name'].'.fields' => 'Create fields for '.$this->curModule['name'],
            $this->curModule['name'].'.panels' => 'Create panels for '.$this->curModule['name'],
            $this->curModule['name'].'.viewfilters' => 'Create view filters for '.$this->curModule['name'],
        ]);

        return $this;
    }

    public function permits($permissionName, $displayName, $description = '')
    {
        $this->curModulePermissions->push(new Permission([
            'name' => $permissionName,
            'displayName' => $displayName,
            'description' => $description,
        ]));

        return $this;
    }

    public function addMainEntity($mainEntity, $hasVf = true)
    {
        $this->curModuleEntity = $mainEntity;
        $this->hasViewFilter($hasVf);

        return $this;
    }

    public function addAutoSubmodules($fieldName, $key = 'name')
    {

        $entity = Entity::where($key, $this->curModuleEntity)->first();
        if (! $entity) {
            throw new \Exception('Error. Unknown entity '.$this->curModuleEntity);
        }
        $field = $entity->fields()->where('name', $fieldName)->where('typeFilter', '!=', null)->first();
        if (! $field) {
            throw new \Exception('Error. Unknown or invalid type filter field named '.$fieldName);
        }
        if (! in_array($field->fieldType->name, ['picklist', 'lookupModel'])) {
            throw new \Exception('Error. Field '.$fieldName.' is of invalid type for autoSubmodule field. Use picklist or lookup only');
        }

        $this->curModule['auto_submodule_source'] = $field->_id;

        return $this;
    }

    public function addQueries(array $queries)
    {

        $mainEntity = $this->curModuleEntity;
        if (! $mainEntity) {
            throw new \Exception('Error. Cannot add query on module without main entity');
        }

        foreach ($queries as $key => $query) {
            [$label, $resolvedQuery] = $this->resolveQuery($query, $mainEntity, $key);
            $this->curQueries->push(new ModuleQuery([
                'name' => $mainEntity.':'.(is_string($key) ? $key : $query),
                'label' => $label,
                'query' => $resolvedQuery,
            ]));
        }

        return $this;
    }

    public function save()
    {
        if ($this->curModule) {
            $this->storeCurrent();
        }

        $names = $this->modules->pluck('name');
        $existing = Module::whereIn('name', $names)->get();

        if ($existing->isNotEmpty()) {
            throw new \Exception('Error. The following modules are already defined: '.$existing->implode('name', ', '), 422);
        }

        $names = collect($this->permissions)->flatten()->pluck('name');
        $existing = Permission::whereIn('name', $names)->get();

        if ($existing->isNotEmpty()) {
            throw new \Exception('Error. The following permissions are already defined: '.$existing->implode('name', ', '), 422);
        }

        $names = collect($this->entities)->flatten();
        $existing = Entity::whereIn('name', $names);
        $nonExisting = $names->diff($existing->pluck('name'));

        if ($nonExisting->isNotEmpty()) {
            throw new \Exception('Error. The following entities do not exist: '.implode(', ', $nonExisting->toArray()), 422);
        }

        $parentModule = null;

        foreach ($this->modules as $module) {

            if (array_key_exists('parent_module', $module)) {
                $parentModule = $module['parent_module'];
                unset($module['parent_module']);
            }

            $newModule = Module::create($module);
            $this->moduleIds[$module['name']] = $newModule->_id;

            if ($parentModule) {
                $newModule->superModule()->associate($this->moduleIds[$parentModule])->save();
                $parentModule = null;
            }

            if (array_key_exists($module['name'], $this->permissions)) {
                $newModule->permissions()->saveMany($this->permissions[$module['name']]);
            }

            if (array_key_exists($module['name'], $this->entities)) {
                $entityName = $this->entities[$module['name']];
                $mainEntity = Entity::where('name', $entityName)->first();
                $newModule->entity()->associate($mainEntity)->save();
            }

            if (array_key_exists($module['name'], $this->queries)) {
                $newModule->queries()->saveMany($this->queries[$module['name']]);
            }

        }

        $this->reset();

        return $this;
    }

    protected function storeCurrent()
    {
        if (! $this->decorated) {
            $this->autoDecorate();
        }

        if (! isset($this->curModule['name'])) {
            throw new \Exception('Error. Trying to save a module that is not yet defined');
        }

        if (array_key_exists('parent_module', $this->curModule)) {
            $superModule = Module::where('name', $this->curModule['parent_module'])->first();
            if (! $superModule) {
                $superModule = Module::where('name', $this->curModule['parent_module'])->first();
            }
            if (! $superModule) {
                throw new \Exception('Error. Module "'.$this->curModule['name'].'" not found');
            }
        }

        if (! array_key_exists('order', $this->curModule) && ! array_key_exists('setup', $this->curModule)) {
            $this->underFolder('top');
        }

        $this->modules->push($this->curModule);

        if ($this->curModuleEntity != null) {
            $this->entities[$this->curModule['name']] = $this->curModuleEntity;
        }

        if ($this->curModulePermissions->isEmpty()) {
            throw new \Exception('Error. No permission defined for module '.$this->curModule['name']);
        }

        $this->permissions[$this->curModule['name']] = $this->curModulePermissions;

        if ($this->curQueries->isNotEmpty()) {
            $this->queries[$this->curModule['name']] = $this->curQueries;
        }

        if (array_key_exists('setup', $this->curModule) && ! array_key_exists('setup_id', $this->curModule)) {
            throw new \Exception('Error. Undefined containing setup for setup module "'.$this->curModule['name'].'"');
        }

        $this->reset(true);
    }

    public function initFolderConfig()
    {

        $this->folderOrders = collect([]);
        $folder = Folder::firstOrCreate([
            'name' => 'top',
            'type_id' => $this->moduleFolderId,
        ], [
            'name' => 'top',
            'label' => 'Top',
            'type_id' => $this->moduleFolderId,
        ]);
        $this->folders = collect([]);

        $this->folders->push($folder);

        return $this;
    }

    public function getFolders()
    {
        $this->folders = Folder::all();

        return $this;
    }

    protected function reset($onlyCurrent = false)
    {
        $this->curModule = null;
        $this->curModulePermissions = collect([]);
        $this->curModuleEntity = null;
        $this->curQueries = collect([]);
        $this->decorated = false;

        if (! $onlyCurrent) {
            $this->modules = collect([]);
            $this->permissions = [];
            $this->entities = [];
            $this->queries = [];
        }
    }

    protected function autoDecorate()
    {
        $this->curModule['icon'] = 'business';
        $this->curModule['color'] = static::$colors[array_rand(static::$colors)];
    }

    protected function resolveQuery($query, $entity, $key)
    {
        if (is_array($query)) {  // labeled query
            if (count($query) != 2) {
                throw new \Exception('Error. Array queries should exactly have two items: (1) query, (2) label');
            }
            $label = $query[0];
            $query = $query[1];
        } elseif (! starts_with($query, 'where')) {
            if (array_key_exists($query, static::$queryPatterns)) {
                $label = str_replace('Entities', str_plural(title_case_from_snake($entity)), static::$queryPatterns[$query][1]);
                $query = str_replace('Entity', $entity, static::$queryPatterns[$query][0]);
            } else {
                throw new \Exception('Unknown common filter query key "'.$query.'"');
            }
        } elseif (is_numeric($key)) {
            throw new \Exception('Error. Unnamed query: '.$query);
        }

        // $this->queryBuilder->setUser($this->user)->selectFrom('*', $entity)->filterGet($query);

        return [
            $label,
            $query,
        ];
    }

    public function addSomething($folderId = null, $order = null, $hasViewFilter = true)
    {
        $this->curModule['order'] = $order;
        $this->curModule['folder_id'] = $folderId;
        $this->curModule['hasViewFilter'] = $hasViewFilter;
        $this->curModule['created_by'] = '5bb104d0678f71061f643d1a';
        $this->curModule['updated_by'] = '5bb104d0678f71061f643d1a';

        return $this;
    }
}
