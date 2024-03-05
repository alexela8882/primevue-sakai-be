<?php

namespace App\Services;

use App\Models\Core\ViewFilter;
use App\Models\Module\Module;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class ViewFilterService
{
    public static function getDefaultViewFilter(User $user, string $moduleName, bool $returnBoolean, Module $module): Collection
    {
        $viewFilters = ViewFilter::query()
            ->where(['moduleName' => $moduleName, 'owner' => $user->_id])
            ->get();

        if ($viewFilters->isEmpty()) {
            $moduleQueries = (new ModuleQueryService)->getUserModuleQueries($user, "{$moduleName}.index", false, $module);

            $viewFilterQuery = ViewFilter::query()
                ->where(['moduleName' => $moduleName, 'owner' => 'default']);

            if (empty($moduleQueries)) {
                $modules = ['products', 'servicereports', 'services', 'employees', 'servicejobs', 'navisionorders'];

                if ($user->can($moduleName.'.index') && ! in_array($moduleName, $modules)) {
                    $defaultViewFilters = $viewFilterQuery->where('query_id', '!=', null)->get();
                } elseif (in_array($moduleName, $modules)) {
                    $defaultViewFilters = $viewFilterQuery->get();
                } else {
                    $defaultViewFilters = collect([]);
                }
            } else {
                $defaultViewFilters = $viewFilterQuery->whereIn('query_id', $moduleQueries)->get();
            }
        } else {
            return $viewFilters;
        }

        if ($defaultViewFilters->isEmpty()) {
            if ($returnBoolean) {
                return false;
            }

            throw new \Exception("Error. No existing default view filter either for this module {$moduleName} or the user's role(s).");
        }

        return $this->copyDefault($defaultViewFilters, $user->_id);
    }

    private static function copyDefault(Collection $defaultViewFilters, $userId): Collection
    {
        $items = collect([]);

        $arrayOfDefaultViewFilters = $defaultViewFilters->toArray();

        foreach ($arrayOfDefaultViewFilters as $defaultViewFilter) {
            unset(
                $defaultViewFilter['_id'],
                $defaultViewFilter['created_at'],
                $defaultViewFilter['updated_at'],
                $defaultViewFilter['created_by'],
                $defaultViewFilter['updated_by']
            );

            $defaultViewFilter['owner'] = $userId;

            $items->push(ViewFilter::create($defaultViewFilter));
        }

        if (! $items->where('isDefault', true)->first()) {
            $items->last()->update(['isDefault' => true]);
        }

        return $items;
    }

    public static function getWidestScope(User $user, string|Module $module, $returnBoolean = false)
    {
        if (! $module instanceof Module) {
            $module = Module::where('name', $module)->with('queries')->first();
        } else {
            $module->load('queries');
        }

        if ($module->hasViewFilter === true) {
            $queries = $module->queries->pluck('_id');

            $viewFilters = self::getDefaultViewFilter($user, $module->name, $returnBoolean, $module);

            if ($returnBoolean && $viewFilters === false) {
                return false;
            }

            if ($queries->isNotEmpty()) {
                foreach ($queries as $query) {
                    foreach ($viewFilters as $viewfilter) {
                        if ($query === $viewfilter->query_id) {
                            return $viewfilter;
                        }
                    }
                }

                throw new Exception('Error. No matching query for module and current user\'s view filters');
            }

            $nullQuery = $viewFilters->firstWhere('query_id', null);

            if ($nullQuery) {
                return $nullQuery;
            }

            return $viewFilters->first();
        }
    }
}
