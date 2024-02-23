<?php

namespace App\Services;

use App\Models\Core\ViewFilter;
use App\Models\Module\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ViewFilterService
{
    public function getDefaultViewFilter(User $user, string $moduleName, bool $returnBoolean, Module $module): Collection
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

    protected function copyDefault(Collection $defaultViewFilters, $userId): Collection
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
}
