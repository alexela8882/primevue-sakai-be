<?php

namespace App\Services;

use App\Http\Resources\ModelCollection;
use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Core\Module;
use App\Models\Core\Panel;
use App\Models\Core\ViewFilter;
use App\Models\Customer\SalesOpportunity;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

use App\Builders\DynamicQueryBuilder;

class ModuleDataCollector
{
    private User $user;

    private Module $module;

    private Collection $fields;

    private Collection $currentViewFilterFields;

    private Collection $panels;

    private $currentViewFilter;

    private $pickLists;

    private $viewFilters;

    private $dqb;

    public function __construct(DynamicQueryBuilder $dqb)
    {
        $this->dqb = $dqb;
    }

    public function setUser()
    {
        $this->user = Auth::guard('api')->user() ?? User::find('5bf45d4a678f714eac558ba3');

        return $this;
    }

    public function setModule(string $name)
    {
        $module = Module::query()
            ->whereName($name)
            ->with([
                'entity',
                'entity.fields',
                'entity.fields.rules',
                'entity.fields.fieldType',
                'entity.fields.relation',
                'entity.fields.relation.entity',
                'entity.fields.relation.entity.fields',
                'permissions',
            ])
            ->first();

        if ($module instanceof Module) {
            $this->module = $module;
        } else {
            throw new Exception("Error. Module named '{$name}' is not found.");
        }

        return $this;
    }

    public function setFields()
    {
        $entity = $this->module->entity;

        if ($entity instanceof Entity) {
            $this->fields = $entity->fields;

            $this->pickLists = (new PicklistService)->getPicklistsFromFields($this->fields);
        } else {
            throw new Exception("Error. Unable to find the main entity of '{$this->module->name}'");
        }
    }

    public function setViewFilters(Request $request)
    {
        if ($this->module->hasViewFilter) {
            $viewFilterQuery = ViewFilter::query()
                ->where('moduleName', $this->module->name)
                ->where('owner', $this->user->_id);

            $viewFilters = $viewFilterQuery->get();

            if ($viewFilters->isEmpty()) {
                $viewFilters = (new ViewFilterService)->getDefaultViewFilter($this->user, "{$this->module->name} .index", false, $this->module);
            }

            $activeViewFilter = $request->input('viewfilter');

            if ($activeViewFilter) {
                $this->currentViewFilter = $viewFilters->firstWhere('_id', $activeViewFilter);

                if ($this->currentViewFilter instanceof ViewFilter) {
                    $this->currentViewFilter->update(['isDefault' => true]);
                }

                $viewFilters->where('_id', '!=', $activeViewFilter)->where('isDefault', true)->update(['isDefault' => false]);

                $viewFilters = $viewFilterQuery->get();
            }

            if (! $this->currentViewFilter instanceof ViewFilter) {
                $this->currentViewFilter = $viewFilters->firstWhere('isDefault', true);

                if (! $this->currentViewFilter instanceof ViewFilter) {
                    $this->currentViewFilter = $viewFilters->first();

                    $this->currentViewFilter->update(['isDefault' => true]);
                }
            }

            if ($request->exists('listview')) {
                $this->currentViewFilterFields = $this->module->entity->fields;
            } else {
                $this->currentViewFilterFields = Field::query()
                    ->whereIn('_id', $this->currentViewFilter->fields)
                    ->with([
                        'relation',
                        'fieldType',
                    ])
                    ->get();
            }
        } else {
            $this->currentViewFilterFields = $this->fields;

            return $this;
        }

        $this->viewFilters = $viewFilters;

        return $this;
    }

    public function setPanels()
    {
        $this->panels = Panel::query()
            ->where('controllerMethod', "{$this->module->name}@index")
            ->orWhere(fn ($query) => $query->where('controllerMethod', "{$this->module->name}@show")->where('mutable', true))
            ->orderBy('order', 'ASC')
            ->get();

        return $this;
    }

    public function getCurrentViewFilterFieldNamesForPagination()
    {
        return $this->currentViewFilterFields->map(fn (Field $field) => $field->name)->toArray();
    }

    public function data(Request $request)
    {
        $this->setFields();

        $this->setViewFilters($request);

        $this->setPanels();

        $model = App::make($this->module->entity->model_class);

       // dd($this->currentViewFilter->filterQuery->query);

        $filterQuery = ($this->module->hasViewFilter && $this->currentViewFilter->filterQuery) ? $this->currentViewFilter->filterQuery->query : null ;
        if($filterQuery) {
            $q = $this->dqb->selectFrom($this->getCurrentViewFilterFieldNamesForPagination(), $this->module->entity->name, true);

            $q = $q->filterGet($filterQuery);
            
            $query = $q;
        }else{
            $query = $model::query();
        }

        $query->where('deleted_at', null);


        $field = $this->module->entity->fields()->where('name', 'branch_id')->count();
    
        if($field){
            $query->whereIn('branch_id', (array) $this->user->handled_branch_ids);
        }

        $page = $request->input('page', 1);

        $pageLength = $request->input('limit', 0);

        $query = $query->paginate($pageLength, $this->getCurrentViewFilterFieldNamesForPagination(), 'page', $page);

        return new ModelCollection($query, $this->module, $this->currentViewFilterFields, $this->panels, $this->viewFilters, $this->pickLists);
    }
}
