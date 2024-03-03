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
use App\Models\Core\ListItem;
use App\Models\Core\Panel;
use App\Models\Core\Picklist;
use App\Models\Core\ViewFilter;
use App\Models\Customer\SalesQuote;
use App\Models\Model\Base;
use App\Models\Module\Module;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    private $staticData; // for Service Jobs. See addStaticConnected function in v1 and find this function for more info

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

            $this->pickLists = PicklistService::getPicklistsFromFields($this->fields);
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
        $query = $this->model->query()->where('deleted_at', null);

        $this->setFields();

        $this->setViewFilters($request);

        $this->setPanels();

        if ($this->module->hasViewFilter && $this->currentViewFilter->filterQuery) {
            $filterQuery = $this->currentViewFilter->filterQuery->query;
        }

        if (isset($filterQuery)) {
            $query = $this->dataQueryBuilder->selectFrom($this->getCurrentViewFilterFieldNamesForPagination(), $this->entity->name, true);

            $query = $query->filterGet($filterQuery);
        }

        $query = $this->getQueryBasedOnHandledBranches($query);

        $query = $this->getQueryBasedOnHeaderFilters($query, $request);

        $query = $this->getQueryBasedOnSearch($query, $request);

        $query = $this->getQueryBasedOnCustomFilters($query, $request);

        $query = $this->getQueryBasedOnCurrentViewFilterSort($query);

        // dd($query->toMql());

        $query = $query->orderBy('name', 'ASC');

        $query = $query->paginate($request->input('limit', 25), $this->getCurrentViewFilterFieldNamesForPagination(), 'page', $request->input('page', 0));

        $modelCollection = new ModelCollection($query, $this->currentViewFilterFields, $this->pickLists);

        if ($request->missing('listOnly')) {
            $modelCollection->additional([
                'fields' => FieldResource::customCollection($this->fields),
                'panels' => PanelResource::customCollection($this->panels),
                'viewFilters' => ViewFilterResource::collection($this->viewFilters),
            ]);
        }

        return $modelCollection;
    }

    public function postStore(Request $request, bool $mainOnly = false)
    {
        $model = null;

        // try {
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
        }

        // elseif (count($formulaFields) !== 0) {
        //     // FOR CHARISSE
        //     FormulaParser::setEntity($this->module->main);

        //     foreach ($formulaFields as $formulaField) {
        //         $value = FormulaParser::parseField($formulaField, $model, true);
        //         $model->update([$formulaField->name => $value]);
        //     }
        // }
        // } catch (Exception $exception) {
        //     if ($mainOnly === false) {
        //         $this->deleteMutableChanges();
        //     }

        //     if ($model) {
        //         $model->delete;
        //     }

        //     throw $exception;
        // }

        return $model;
    }

    // getConnectedCollectionData v1
    public function getShow(Base $base, Request $request, bool $isItemOnly = false, bool $isConnectedOnly = false, array $additional = [])
    {
        $data = [];

        $deepMutables = [];

        $connectedEntitiesList = collect();

        $this->setFields();

        if (! $isItemOnly && $request->exists('itemonly')) {
            $isItemOnly = true;
        }

        if (! $isItemOnly) {
        }

        if ($request->exists('connectedonly') || $request->exists('cname') || $isConnectedOnly) {
            $data = ['connected' => $connectedEntitiesList];
        } else {
            if (! $isItemOnly) {
                if ($this->staticData) { // For Service Job
                    $connectedEntitiesList = $connectedEntitiesList->merge($this->staticData);
                }

                $data['connected'] = $connectedEntitiesList;
            }

            $modelResource = new ModelResource($base, $this->fields, $this->pickLists);

            if ($request->exists('withModulePanels')) {
                $this->setPanels();

                $modelResource->additional([
                    'panels' => PanelResource::customCollection($this->panels),
                ]);
            }

            return $modelResource;
        }
    }

    public function getRelatedList()
    {
        dd($this->entity);
        $this->entity->entityConnection?->entities
            ->map(function (Entity $entity) {
                dd($entity->relationLoaded('fields'));
                $entity->fields
                    ->filter(fn (Field $field) => $field->fieldType->name === 'lookupModel')
                    ->filter(fn (Field $field) => dd($field->relationLoaded('relation')));
            });
    }

    public function getQueryBasedOnHandledBranches($query)
    {
        // Fetch data based on user's handled branches
        // No fetching need if ignorePermission of the currentViewFilter is true

        if ($this->currentViewFilter->ignorePermission === false) {
            $hasBranchField = $this->fields->contains('name', 'branch_id');

            if ($hasBranchField) {
                return $query->whereIn('branch_id', $this->user->handled_branch_ids);
            }
        }

        return $query;
    }

    public function getQueryBasedOnSearch($query, Request $request)
    {
        if ($request->filled('search')) {
            $searchFields = (array) $request->input('searchFields');

            // Must have at least 1 search field to run this query below
            $query->where(function (Builder $query) use ($searchFields, $request) {
                foreach ($searchFields as $searchField) {
                    $field = $this->fields->firstWhere('_id', $searchField);

                    if ($field->fieldType->name === 'lookupModel') {
                    } elseif ($field->fieldType->name === 'picklist') {
                        $items = Picklist::query()
                            ->where('name', $field->listName)
                            ->with('listItems')
                            ->first()
                            ->listItems
                            ->filter(fn (ListItem $listItem) => Str::contains($listItem->value, $request->input('search'), true))
                            ->pluck('_id');

                        if ($items->isNotEmpty()) {
                            $query->orWhereIn($field->name, $items);
                        }
                    } elseif ($field->fieldType->name === 'boolean') {
                        // need to discuss this first with team
                        // e.g. products index where Status at top
                    } else {
                        $query->orWhere($field->name, 'like', "%{$request->input('search')}%");
                    }
                }
            });
        }

        return $query;
    }

    public function getQueryBasedOnHeaderFilters($query, Request $request)
    {
        if ($request->filled('header_filters')) {
            $headerFilters = $request->input('header-filters');

            if (is_array($headerFilters) && array_depth($headerFilters) > 2) {
                foreach ($headerFilters as $headerFilter) {
                    if (array_key_exists('field', $headerFilter) && array_key_exists('values', $headerFilter)) {
                        $query->whereIn($headerFilter['field'], $headerFilter['values']);
                    }
                }
            }
        }

        return $query;
    }

    public function getQueryBasedOnCustomFilters($query, Request $request)
    {
        return $query;
    }

    public function getQueryBasedOnCurrentViewFilterSort($query)
    {
        return $query;
    }

    public function getDataForSaving(Request $request, mixed $model = null, bool $quickAdd = false, bool $isCreate = false)
    {
        // $isCreate is true = for saving
        // $isCreate is false = for updating

        $data = [];

        $lookupData = [];

        $lookupModels = [];

        $formulaFields = [];

        $this->setFields();

        if ($quickAdd) {
            $fields = $this->fields->filter(fn (Field $field) => $field->quick === true || $field->rules->whereIn('name', ['required', 'required_if', 'required_with', 'required_without', 'required_unless', 'owner_id'])->isNotEmpty());
        } else {
            $fields = $this->fields;
        }

        if (! $quickAdd && ! $isCreate) {
            $data['updated_by'] = $this->user?->_id;
        }

        foreach ($fields as $field) {
            if ($request->missing($field->name)) {
                continue;
            }

            if ($field->fieldType->name === 'autonumber' && ! $isCreate) {
                continue;
            }

            if (in_array($field->name, ['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by'])) {
                continue;
            }

            $input = $request->input($field->name, []);

            if ($field->fieldType->name === 'lookupModel') {
                $returnedResult = $this->fieldService->resolveExecuteLookupField($request, $model, $input, $this->entity, $field, $lookupModels);

                if ($returnedResult === false) {
                    continue;
                } elseif (is_array($returnedResult)) {
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
            } elseif ($field->fieldType->name === 'autonumber' && $isCreate) {
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
                    $items = Picklist::whereIn('name', ['rp_type', 'rp_form_types'])->get()->map(function ($list) {
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

    private function deleteMutableChanges($upsert = false)
    {
        if ($this->mutableEntityNames) {
            foreach ($this->mutableEntityNames as $mutableEntityName) {
                $items = $this->mutableData[$mutableEntityName];
                if (count($items)) {
                    foreach ($items as $item) {
                        $item->delete();
                    }
                }
                if ($upsert) {
                    foreach ($this->mutableEntityNames as $mutableEntityName) {
                        $items = $this->revertibleMutableData[$mutableEntityName];
                        $entity = Entity::where('name', $mutableEntityName)->first();
                        $entityModel = $entity->getModel();
                        $entityModel::unguard();

                        foreach ($items as $item) {
                            $entityModel = $entityModel->find($item['_id']);
                            $entityModel->update($item);
                        }

                        $entityModel::reguard();
                    }
                }
            }
        }
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

        $mutableEntities = $this->entity->deepConnectedEntities(true, 1)->whereIn('name', $this->mutableEntityNames);

        $this->createMutable($model, $this->entity, $mutables, $mutableEntities, 1, $isUpsert, $isQuickAdd);

        return [
            'created' => $this->mutableData,
            'updated' => $this->revertibleMutableData,
        ];
    }

    protected function isMutableFilter(Field $field, bool $isQuickAdd = false)
    {
        $result = ! in_array($field->name, ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at']);

        if ($isQuickAdd) {
            $result = $result && $field->rules->whereIn('name', ['required', 'required_if', 'required_with'])->count() > 1;
        }

        return $result;
    }

    protected function resolveRow($row, $fields)
    {
        $xx = $fields->pluck('name')->toArray();
        $xx = array_diff(array_keys($row), $xx);

        foreach ($xx as $key) {
            unset($row[$key]);
        }

        $row['__state'] = 'pending';

        return $row;
    }

    protected function getMainDataFromRowMutable($row, $fields, $model, $quickAdd, $entity)
    {
        $lookupData = [];
        $lookupModels = [];

        foreach ($fields as $field) {
            if (array_key_exists($field->name, $row)) {
                if ($field->fieldType->name == 'lookupModel') {
                    $result = $this->resolveExecuteLookupField($row, $model, $row[$field->name], $entity, $field, $lookupModels);
                    if ($result === false) {
                        continue;
                    } elseif (is_array($result)) {
                        $lookupData[] = $result;

                        continue;
                    }
                }
            }
        }

        return $lookupData;
    }

    protected function resolveExecuteLookupField($request, $model, &$input, $entity, $field, &$lookupModels)
    {
        $isRequired = $field->rules->where('name', 'required')->first();

        $input = (array) (($input == '') ? null : $input);

        $relation = $field->relation;

        $query = $relation->relatedEntity->getModel();

        // if lookup is a controlling field, store model
        if ($field->controls) {
            $lookupModels[$field->name] = $query;
        }

        $itemIds = $query->whereIn('_id', $input)->pluck('_id')->toArray();

        if (count($diff = array_diff($input, $itemIds)) && $isRequired) {
            throw new \Exception('Error. Invalid ids for field '.$field->name.': ["'.implode('", "', $diff).'"]');
        }

        if ($field->uniqueName != 'serviceschedule_branch_id') {
            $existingItems = $field->relation->relatedEntity->getModel()->whereIn('_id', $input)->get();
            $unknownItems = collect($input)->diff($existingItems->pluck('_id'));

            if ($unknownItems->isNotEmpty()) {
                throw new \Exception('Error. The following items for field "'.$field->name.'" are unidentified: '.implode(',', $unknownItems->toArray()));
            }
        }

        $arrReq = is_array($request) ? $request : $request->all();

        // if lookup has a controlling field
        if (isset($field->rules['filtered_by'])) {
            $cFieldName = $field->rules['filtered_by'];

            if (! isset($lookupModels[$cFieldName])) {
                $cField = $entity->fields->where('name', $cFieldName)->first();

                if (! $cField) {
                    throw new \Exception('Error. Unrecognized controlling field '.$cFieldName);
                }
            }

            $cvalue = $request['$cFieldName'];

            $unknownItems = $existingItems->whereNotIn($field->filterSourceField, (array) $cvalue)->get();

            if ($unknownItems->isNotEmpty()) {
                throw new \Exception('Error.  The following items for field "'.$field->name.'" are incompatible with controlling field: '.implode(',', $unknownItems->toArray()));
            }
        }

        $isEmpty = ($input == null || $input == '' || is_array($input) && ! count($input));

        if ($isEmpty) {
            if ($isRequired) {
                throw new \Exception('Field '.$field->name.' is required');
            } elseif ($model || array_key_exists($field->name, $arrReq)) {
                $input = null;
            } else {
                return false;
            }
        }

        if ($field->hasMultipleValues()) {
            return [
                'entity' => $field->relation->relatedEntity->model_class,
                'method' => $field->relation->method,
                'fkey' => $field->relation->foreign_key,
                'lkey' => $field->relation->local_key,
                'data' => $input,
            ];
        }

        return true;
    }

    public function compute($model, $rus, $formula)
    {
        $update = [];

        if (count($rus)) {
            foreach ($rus as $rusField) {
                $v = $model->{$rusField->name} ?? null;

                $value = RusResolver::resolve($model, $rusField);

                $update[$rusField->name] = $value;
            }

            //   if($model->branch_id == '5badf748678f7111186ba275'){
            //     dump($update);
            //   }

            $model->update($update);
        }

        $model->save();

        if (count($formula)) {
            foreach ($formula as $formulaField) {
                $value = FormulaParser::parseField($formulaField, $model, true);
                $model->update([$formulaField->name => $value]);
            }
        }
    }

    protected function createMutable($model, $currentEntity, $requestMutables, $mutableEntities, $level, bool $isUpsert = false, $isQuickAdd = false)
    {
        foreach ($mutableEntities as $mutableEntity) {
            $fields = $mutableEntity->fields->filter(fn (Field $field) => $this->isMutableFilter($field, $isQuickAdd));

            $repository = $mutableEntity->getRepository();

            $mutableList = $requestMutables["mutable_{$mutableEntity->name}"];

            if (empty($mutableList)) {
                $panel = Panel::query()
                    ->where('entity_id', $mutableEntity->_id)
                    ->where('controllerMethod', "{$this->module->name}@show")
                    ->where('mutable', true)
                    ->first();

                if ($panel->required) {
                    throw new Exception("Error. Missing data for required mutable {$mutableEntity->name}");
                }
            }

            // FOR CHARISSE
            if ($mutableEntity->name == 'SalesOpptItem') {
                RusResolver::setEntity($entity);
                FormulaParser::setEntity($entity);
                $fields = $entity->fields()->get();
                [$formula, $rus] = $this->getRusAndFormula($fields);
            }

            foreach ($mutableList as $key => $mutableRow) {
                $mutableRow = (array) $mutableRow;

                if ($level > 1) {
                    if (! array_key_exists("{$currentEntity->name}_key", $mutableRow)) {
                        throw new Exception("Error. Missing parent entity key {$currentEntity->name}_key in mutable_{$mutableEntity->name}");
                    }

                    $parentEntityKey = $mutableRow["{$currentEntity->name}_key"];

                    if ($isUpsert && array_key_exists('_id', $mutableRow)) {
                        if (array_key_exists($parentEntityKey, $this->revertibleMutableData[$currentEntity->name])) {
                            $model = $this->revertibleMutableData[$currentEntity->name][$parentEntityKey];
                        } elseif (array_key_exists($parentEntityKey, $this->mutableData[$currentEntity->name])) {
                            $model = $this->mutableData[$currentEntity->name][$parentEntityKey];
                        } else {
                            throw new Exception("Missing key {$parentEntityKey}");
                        }
                    } else {
                        if (! array_key_exists($parentEntityKey, $this->mutableData[$currentEntity->name])) {
                            throw new Exception("Missing key {$parentEntityKey}");
                        }

                        $model = $this->mutableData[$currentEntity->name][$parentEntityKey];
                    }
                }

                $newData = $this->resolveRow($mutableRow, $fields);

                $ldata = $this->getMainDataFromRowMutable($mutableRow, $fields, null, false, $mutableEntity);

                if (! array_key_exists(snake_case($currentEntity->name).'_id', $newData)) {
                    $newData[snake_case($currentEntity->name).'_id'] = $model['_id'];
                }

                if ($isUpsert && array_key_exists('_id', $mutableRow)) {
                    $item = $repository->find($mutableRow['_id']);

                    if (array_key_exists(snake_case($currentEntity->name).'_id', $item->toArray()) && $item->toArray()[snake_case($currentEntity->name).'_id']) {
                        $newData[snake_case($currentEntity->name).'_id'] = $item->toArray()[snake_case($currentEntity->name).'_id'];
                    }

                    $this->revertibleMutableData[$mutableEntity->name][$key] = $item->toArray();

                    foreach ($ldata as $lookup) {
                        $query = $item->dynamicRelationship($lookup['method'], $lookup['entity'], $lookup['fkey'], $lookup['lkey'], null, true);
                        if ($lookup['method'] == 'belongsToMany') {
                            if ($lookup['data']) {
                                $item->update([$lookup['lkey'] = $lookup['data']]);
                            } else {
                                $query->detach();
                            }
                        } elseif ($lookup['data']) {
                            $query->associate($lookup['data']);
                        } else {
                            $query->dissociate();
                        }
                    }

                    $item->update($newData);
                } else {
                    $item = $repository->create($newData);
                    $item->update(['oid' => $item->_id]);
                    foreach ($ldata as $lookup) {
                        $query = $item->dynamicRelationship($lookup['method'], $lookup['entity'], $lookup['fkey'], $lookup['lkey'], null, true);

                        if ($lookup['method'] == 'belongsToMany') {
                            if ($lookup['data']) {

                                if ($item->{$lookup['lkey']}) {
                                    if (is_string($item->{$lookup['lkey']})) {
                                        $item->update([$lookup['lkey'] => (array) $item->{$lookup['lkey']}]);
                                    }
                                    $query->sync($lookup['data']);
                                } else {
                                    $query->attach($lookup['data']);
                                }
                            } else {
                                $query->detach();
                            }
                        } elseif ($lookup['data']) {
                            $query->associate($lookup['data']);
                        }
                    }

                    $this->mutableData[$mutableEntity->name][$key] = clone $item;
                }

                if ($mutableEntity->name == 'SalesOpptItem') {
                    $this->compute($item, $rus, $formula);
                }
            }
            $deepMutableEntities = $mutableEntity->deepConnectedEntities(true, 1)->whereIn('name', $this->mutableEntityNames);

            if ($deepMutableEntities->isNotEmpty()) {
                $this->createMutable($model, $mutableEntity, $requestMutables, $deepMutableEntities, $level + 1, $isUpsert, $isQuickAdd);
            }
        }
    }

    public function getRusAndFormula($fields, $query = null)
    {
        $formulaFields = [];
        $rusField = [];

        foreach ($fields as $field) {
            if ($field->fieldType->name == 'formula') {
                $formulaFields[] = $field;

                continue;
            }

            if ($field->fieldType->name == 'rollUpSummary') {
                if ($query) {
                    if ($field->rusEntity == $query) {
                        $rusField[] = $field;
                    }
                } else {
                    $rusField[] = $field;
                }

                continue;
            }
        }

        return [$formulaFields, $rusField];
    }
}
