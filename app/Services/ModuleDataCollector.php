<?php

namespace App\Services;

use App\Builders\DynamicQueryBuilder;
use App\Http\Resources\Core\FieldResource;
use App\Http\Resources\Core\ModelResource;
use App\Http\Resources\Core\PanelResource;
use App\Http\Resources\Core\ViewFilterResource;
use App\Http\Resources\ModelCollection;
use App\Models\Company\Branch;
use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Core\Panel;
use App\Models\Core\ViewFilter;
use App\Models\Model\Base;
use App\Models\Module\Module;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ModuleDataCollector
{
    public User $user;

    private Module $module;

    public Collection $fields;

    private Collection $currentViewFilterFields;

    private Collection $panels;

    private $currentViewFilter;

    public $pickLists;

    private $viewFilters;

    public function __construct(private DynamicQueryBuilder $dataQueryBuilder, private FieldService $fieldService)
    {
        //
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

    public function getIndex(Request $request)
    {
        $this->setFields();

        $this->setViewFilters($request);

        $this->setPanels();

        $model = App::make($this->module->entity->model_class);

        // dd($this->currentViewFilter->filterQuery->query);

        if ($this->module->hasViewFilter && $this->currentViewFilter->filterQuery) {
            $filterQuery = $this->currentViewFilter->filterQuery->query;
        }

        if (isset($filterQuery)) {
            $query = $this->dataQueryBuilder->selectFrom($this->getCurrentViewFilterFieldNamesForPagination(), $this->module->entity->name, true);

            $query = $query->filterGet($filterQuery);

            $query = $query;
        } else {
            $query = $model::query();
        }

        $query->where('deleted_at', null);

        $field = $this->module->entity->fields->where('name', 'branch_id')->count();

        if ($field) {
            $query->whereIn('branch_id', (array) $this->user->handled_branch_ids);
        }

        $page = $request->input('page', 1);

        $pageLength = $request->input('limit', 0);

        $query = $query->paginate($pageLength, $this->getCurrentViewFilterFieldNamesForPagination(), 'page', $page);

        return (new ModelCollection(
            $query,
            $this->currentViewFilterFields,
            $this->pickLists,
        ))
            ->additional([
                'fields' => FieldResource::collection($this->currentViewFilterFields),
                'panels' => PanelResource::collection($this->panels),
                'viewFilters' => ViewFilterResource::collection($this->viewFilters),
            ]);
    }

    public function postStore(Request $request, bool $mainOnly = false)
    {
        [$data, $lookupData, $formulaFields] = $this->resolveRequestForSaving($request, null, false, true);

        if (! $request->missing('uid')) {
            $data['uid'] = $request->input('uid');
        }

        $model = App::make($this->module->entity->model_class);

        $model = $model->create($data);

    }

    public function getShow(Base $base, Request $request, bool $isItemOnly = false, bool $isConnectedOnly = false, array $additional = [])
    {
        $data = [];
        $deepMutables = [];
        $connectedEntitiesList = collect();

        $this->setFields();

        if ($isItemOnly === false && $request->exists('itemonly')) {
            $isItemOnly = true;
        }

        if ($isItemOnly === false) {

        }

        if ($request->exists('connectedonly') || $request->exists('cname') || $isConnectedOnly === true) {
            $data = ['connected' => $connectedEntitiesList];
        } else {
            ModelResource::information($this->fields, $this->pickLists);

            return new ModelResource($base);
        }
    }

    public function resolveRequestForSaving(Request $request, mixed $model = null, bool $quickAdd = false, bool $isCreate = false)
    {
        $data = [];
        $lookupData = [];
        $formulaFields = [];

        $this->setFields();

        if ($quickAdd) {
            $fields = $this->fields->filter(fn (Field $field) => $field->quick === true || $field->rules->whereIn('name', ['required', 'required_if', 'required_with', 'required_without', 'required_unless', 'owner_id'])->isNotEmpty());
        } else {
            $fields = $this->fields;
        }

        if ($quickAdd === false && $isCreate === false) {
            if ($this->user instanceof User) {
                $data['updated_by'] = $this->user->_id;
            }
        }

        foreach ($fields as $field) {
            $input = $request->input($field->name);

            if (
                $request->exists($field->name) === false &&
                $field->fieldType->name != 'autonumber' &&
                $field->fieldType->name != 'formula' ||
                in_array($field->name, ['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by'])
            ) {
                continue;
            } elseif ($field->fieldType->name === 'autonumber' && $isCreate === false) {
                continue;
            }

            if ($field->fieldType->name === 'lookupModel') {
                $returnedResult = $this->fieldService->resolveExecuteLookupField($request, $field, null);

                if ($returnedResult === false) {
                    continue;
                } else {
                    $lookupData[] = $returnedResult;

                    continue;
                }
            } elseif ($field->fieldType->name === 'formula') {
                if ($isCreate && $field->hasRUS) {
                    continue;
                }

                $formulaFields[] = $field;

                continue;
            }

            if ($this->fieldService->hasMultipleValues($field) || $field->fieldType->name === 'list' || $field->fieldType->name === 'chipbox') {
                $data[$field->name] = [$input];
            } elseif ($field->fieldType->name === 'autonumber' && $isCreate === true) {
                // TO BE CLEANED LATER
                if ($field->uniqueName == 'salesquote_quote_no') {
                    $branch_id = $request->input('branch_id');
                    $countryCode = Branch::find($branch_id);
                    $countryCode = $countryCode->quote_no_prefix ?? false ? $countryCode->quote_no_prefix : $countryCode->country->alpha2Code;
                    $s = $this->module->main->getModel()->where('quoteNo', 'like', $countryCode.'-'.date('y').'%')->count();
                    do {
                        $s += 1;
                        $code = $countryCode.'-'.date('y').sprintf('%05s', $s);
                        $check = $this->module->main->getModel()->where('quoteNo', $code)->count();
                    } while ($check);

                    $data[$field->name] = $code;
                } elseif ($field->uniqueName === 'defectreport_drf_code') {
                    $count = $field->entity->getModel()->count($field->name, 'like', date('Y').'-');
                    $data[$field->name] = date('Y').'-'.sprintf('%05s', $count + 1);
                } elseif ($field->uniqueName === 'defectreport_drf_no') {
                    $count = $field->entity->getModel()->count($field->name, 'like', date('y').'-');
                    $data[$field->name] = date('y').'-'.Carbon::now()->weekOfYear.'-'.sprintf('%05s', $count + 1);
                } elseif ($field->uniqueName === 'rpworkorder_case_i_d') {
                    $previousRPOrder = $field->entity->getModel()->withTrashed()->where('defect_report_id', $request->defect_report_id)->first();

                    if (! empty($previousRPOrder)) {
                        $data[$field->name] = $previousRPOrder->caseID;
                    } else {
                        $count = $field->entity->getModel()->withTrashed()->select('caseID')->distinct()->get()->count();
                        $data[$field->name] = date('y').'-'.sprintf('%04s', $count + 1);
                    }
                } elseif ($field->uniqueName === 'rpworkorder_rp_no') {
                    $items = $this->pickListRepository->getModel()->whereIn('name', ['rp_type', 'rp_form_types'])->get()->map(function ($list) {
                        return $list->listItems->map(function ($item) use ($list) {
                            $explodedValue = explode(' ', $item->value);
                            $item->value = $list->name == 'rp_type'
                                ? $explodedValue[0][0]
                                : $explodedValue[0][0].$explodedValue[1][0];

                            return $item;
                        });
                    })->collapse()->pluck('value', '_id')->toArray();

                    $count = $field->entity->getModel()->withTrashed()->where('rpNo', 'like', '%'.$items[$request->form_type_id].'-%')->count();

                    if ($items[$request->form_type_id] == 'RP') {
                        $count = $count - 116;
                        $count = $count + 13361;
                    } elseif ($items[$request->form_type_id] == 'SP') {
                        $count = $count - 19;
                        $count = $count + 236;
                    } elseif ($items[$request->form_type_id] == 'NP') {
                        $count = $count - 3;
                        $count = $count + 5;
                    }

                    $data[$field->name] = $items[$request->form_type_id].'-'.sprintf('%05s', $count).'-'.$items[$request->rp_type_id];
                } elseif ($field->uniqueName === 'breakdownlog_ref_no') {
                    $branch_id = $request->get('branch_id');
                    $countryCode = Branch::find($branch_id);
                    $s = $this->module->main->getModel()->where('branch_id', $branch_id)->where($field->name, 'like', date('Y').date('m').'%')->count();
                    do {
                        $s += 1;
                        $code = date('Y').date('m').'-'.sprintf('%03s', $s);
                        $check = $this->module->main->getModel()->where('branch_id', $branch_id)->where($field->name, $code)->count();
                    } while ($check);

                    $data[$field->name] = $code;
                } else {
                    $s = $this->module->main->getModel()->all()->count();
                    $data[$field->name] = date('Y').date('m').'-'.sprintf('%06s', $s);
                }
            } else {
                $data[$field->name] = is_array($input) && count($input)
                    ? $input[0]
                    : $input;
            }
        }

        return [$data, $lookupData, $formulaFields];
    }
}
