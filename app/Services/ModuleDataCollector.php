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
use App\Models\Customer\SalesQuote;
use App\Models\Model\Base;
use App\Models\Module\Module;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ModuleDataCollector
{
    public User $user;

    private Module $module;

    private Entity $entity;

    private Base $model;

    public Collection $fields;

    private Collection $currentViewFilterFields;

    public Collection $panels;

    private $currentViewFilter;

    public $pickLists;

    private $viewFilters;

    private array $mutableData;

    private array $revertibleMutableData;

    private array $mutableEntityNames;

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

            $this->entity = $module->entity;

            $this->model = App::make($module->entity->model_class);
        } else {
            throw new Exception("Error. Module named '{$name}' is not found.");
        }

        return $this;
    }

    public function setFields()
    {
        $entity = $this->entity;

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

            if (!$this->currentViewFilter instanceof ViewFilter) {
                $this->currentViewFilter = $viewFilters->firstWhere('isDefault', true);

                if (!$this->currentViewFilter instanceof ViewFilter) {
                    $this->currentViewFilter = $viewFilters->first();

                    $this->currentViewFilter->update(['isDefault' => true]);
                }
            }

            if ($request->exists('listview')) {
                $this->currentViewFilterFields = $this->entity->fields;
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

        $model = $this->model->query()->where('deleted_at', null);

        // dd($this->currentViewFilter->filterQuery->query);

        if ($this->module->hasViewFilter && $this->currentViewFilter->filterQuery) {
            $filterQuery = $this->currentViewFilter->filterQuery->query;
        }

        if (isset($filterQuery)) {
            $query = $this->dataQueryBuilder->selectFrom($this->getCurrentViewFilterFieldNamesForPagination(), $this->entity->name, true);

            $query = $query->filterGet($filterQuery);

            $query = $query;
        } else {
            $query = $model::query();
        }

        $field = $this->module->entity->fields->where('name', 'branch_id')->count();

        if ($field) {
            $query = $query->whereIn('branch_id', (array) $this->user->handled_branch_ids);
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
        $model = null;

        try {
            // This is for FE's tracking wherein they will create a unique indentifier,
            // and see if this entity's model's uid already exists or not yet.
            // If it is already existing, then we will just return the model
            // to let FE know that it already exist.
            if ($request->filled('uid')) {
                $uniqueIdForFE = $this->model->where('uid', $request->input('uid'))->first();

                if ($uniqueIdForFE) {
                    return $uniqueIdForFE;
                }
            }

            [$data, $lookupData, $formulaFields] = $this->getDataForSaving($request, null, false, true);

            // If uid is not yet present, then we will add this uid value for saving.
            if ($request->filled('uid')) {
                $data['uid'] = $request->input('uid');
            }

            // Adding fullName attribute to the following entities for saving.
            if (in_array($this->entity->name, ['Contact', 'Employee', 'User', 'EscoVenturesContact'])) {
                $data['fullName'] = Str::squish("{$request->input('firstName')} {$request->input('lastName')}");
            }

            $model = $this->model->create($data);

            foreach ($lookupData as $lookup) {
                $query = $model->dynamicRelationship($lookup['method'], $lookup['entity'], $lookup['fkey'], $lookup['lkey'], null, true);

                if ($lookup['method'] === 'belongsToMany' && $lookup['data']) {
                    $query->sync($lookup['data']);
                } else {
                    $query->associate($lookup['data']);
                }
            }

            if ($this->entity->name === 'SalesOpportunity') {
                $model->quotations->each(fn (SalesQuote $salesQuote) => $salesQuote->updateQuietly(['sales_type_id' => $model->sales_type_id]));
            }

            $hasMutable = (new EntityService)->hasMutable($this->entity);

            if ($mainOnly === false && $hasMutable) {
                $this->executeMutableDataFromRequest($request, $model);
            } elseif (count($formulaFields) !== 0) {
                // FOR CHA
                FormulaParser::setEntity($this->module->main);

                foreach ($formulaFields as $formulaField) {
                    $value = FormulaParser::parseField($formulaField, $model, true);
                    $model->update([$formulaField->name => $value]);
                }
            }
        } catch (Exception $exception) {
            if ($mainOnly === false) {
            }
        }

        return $model;
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

    public function getDataForSaving(Request $request, mixed $model = null, bool $quickAdd = false, bool $isCreate = false)
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
                ($request->missing($field->name) && $field->fieldType->name != 'autonumber' && $field->fieldType?->name != 'formula') ||
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
                    $s = $this->module->main->getModel()->where('quoteNo', 'like', $countryCode . '-' . date('y') . '%')->count();
                    do {
                        $s += 1;
                        $code = $countryCode . '-' . date('y') . sprintf('%05s', $s);
                        $check = $this->module->main->getModel()->where('quoteNo', $code)->count();
                    } while ($check);

                    $data[$field->name] = $code;
                } elseif ($field->uniqueName === 'defectreport_drf_code') {
                    $count = $field->entity->getModel()->count($field->name, 'like', date('Y') . '-');
                    $data[$field->name] = date('Y') . '-' . sprintf('%05s', $count + 1);
                } elseif ($field->uniqueName === 'defectreport_drf_no') {
                    $count = $field->entity->getModel()->count($field->name, 'like', date('y') . '-');
                    $data[$field->name] = date('y') . '-' . Carbon::now()->weekOfYear . '-' . sprintf('%05s', $count + 1);
                } elseif ($field->uniqueName === 'rpworkorder_case_i_d') {
                    $previousRPOrder = $field->entity->getModel()->withTrashed()->where('defect_report_id', $request->defect_report_id)->first();

                    if (!empty($previousRPOrder)) {
                        $data[$field->name] = $previousRPOrder->caseID;
                    } else {
                        $count = $field->entity->getModel()->withTrashed()->select('caseID')->distinct()->get()->count();
                        $data[$field->name] = date('y') . '-' . sprintf('%04s', $count + 1);
                    }
                } elseif ($field->uniqueName === 'rpworkorder_rp_no') {
                    $items = $this->pickListRepository->getModel()->whereIn('name', ['rp_type', 'rp_form_types'])->get()->map(function ($list) {
                        return $list->listItems->map(function ($item) use ($list) {
                            $explodedValue = explode(' ', $item->value);
                            $item->value = $list->name == 'rp_type'
                                ? $explodedValue[0][0]
                                : $explodedValue[0][0] . $explodedValue[1][0];

                            return $item;
                        });
                    })->collapse()->pluck('value', '_id')->toArray();

                    $count = $field->entity->getModel()->withTrashed()->where('rpNo', 'like', '%' . $items[$request->form_type_id] . '-%')->count();

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

                    $data[$field->name] = $items[$request->form_type_id] . '-' . sprintf('%05s', $count) . '-' . $items[$request->rp_type_id];
                } elseif ($field->uniqueName === 'breakdownlog_ref_no') {
                    $branch_id = $request->get('branch_id');
                    $countryCode = Branch::find($branch_id);
                    $s = $this->module->main->getModel()->where('branch_id', $branch_id)->where($field->name, 'like', date('Y') . date('m') . '%')->count();
                    do {
                        $s += 1;
                        $code = date('Y') . date('m') . '-' . sprintf('%03s', $s);
                        $check = $this->module->main->getModel()->where('branch_id', $branch_id)->where($field->name, $code)->count();
                    } while ($check);

                    $data[$field->name] = $code;
                } else {
                    $s = $this->module->main->getModel()->all()->count();
                    $data[$field->name] = date('Y') . date('m') . '-' . sprintf('%06s', $s);
                }
            } else {
                $data[$field->name] = is_array($input) && count($input)
                    ? $input[0]
                    : $input;
            }
        }

        return [$data, $lookupData, $formulaFields];
    }

    public function executeMutableDataFromRequest(Request $request, &$model, bool $isUpsert = false, bool $isQuickAdd = false)
    {
        $mutables = $request->input('mutables');

        $this->mutableData = [];

        if ($isUpsert) {
            $this->revertibleMutableData = [];
        }

        $this->mutableEntityNames = [];

        if (! $mutables) {
            return [[], []];
        }

        foreach (array_keys($mutables) as $mutable) {
            $mutableEntityName = str_replace('mutable_', '', $mutable);
            $this->mutableEntityNames[] = $mutableEntityName;
            $this->mutableData[$mutableEntityName] = [];
            $this->revertibleMutableData[$mutableEntityName] = [];
        }

        $mutableEntities = (new EntityService)->deepConnectedEntities($this->entity, true, 1)->whereIn('name', $this->mutableEntityNames);

        $this->createMutable($model, $this->entity, $mutables, $mutableEntities, 1, $isUpsert, $isQuickAdd);

        return [
            'created' => $this->mutableData,
            'updated' => $this->revertibleMutableData,
        ];
    }
}
