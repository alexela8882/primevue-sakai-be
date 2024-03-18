<?php

namespace App\Services;

use App\Models\Core\ViewFilter;
use App\Models\Module\Module;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

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

            if ($moduleQueries->isEmpty()) {
                $modules = ['products', 'servicereports', 'services', 'employees', 'servicejobs', 'navisionorders'];

                if ($user->canView($moduleName) && ! in_array($moduleName, $modules)) {
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

        return self::copyDefault($defaultViewFilters, $user->_id);
    }

    private static function copyDefault(Collection $defaultViewFilters, $userId): Collection
    {
        $defaultViewFilters
            ->each(function (ViewFilter $viewFilter) use ($defaultViewFilters, $userId) {
                $viewFilterData = Arr::except($viewFilter->toArray(), ['_id', 'created_at', 'updated_at', 'created_by', 'updated_by']);

                $viewFilterData['owner'] = $userId;

                $newViewFilter = ViewFilter::create($viewFilterData);

                $defaultViewFilters->push($newViewFilter);
            });

        $exists = $defaultViewFilters->contains('isDefault', '=', true);

        if (! $exists) {
            $defaultViewFilters->last()->update(['isDefault' => true]);
        }

        return $defaultViewFilters;

        // $items = collect([]);

        // $arrayOfDefaultViewFilters = $defaultViewFilters->toArray();

        // foreach ($arrayOfDefaultViewFilters as $defaultViewFilter) {
        //     unset(
        //         $defaultViewFilter['_id'],
        //         $defaultViewFilter['created_at'],
        //         $defaultViewFilter['updated_at'],
        //         $defaultViewFilter['created_by'],
        //         $defaultViewFilter['updated_by']
        //     );

        //     $defaultViewFilter['owner'] = $userId;

        //     $items->push(ViewFilter::create($defaultViewFilter));
        // }

        // if (!$items->where('isDefault', true)->first()) {
        //     $items->last()->update(['isDefault' => true]);
        // }

        // return $items;
    }

    public static function getWidestScope(User $user, null|string|Module $module, $returnBoolean = false)
    {
        if (! $module instanceof Module) {
            $module = Module::where('name', $module)->with('queries')->first();
        } else {
            $module->load('queries');
        }

        if (($module->hasViewFilter ?? null) === true) {
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

        return true;
    }
}
