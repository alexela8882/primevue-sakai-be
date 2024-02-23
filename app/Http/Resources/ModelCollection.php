<?php

namespace App\Http\Resources;

use App\Models\Core\Country;
use App\Models\Core\Module;
use App\Models\Model\Base;
use App\Services\FieldService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ModelCollection extends ResourceCollection
{
    public function __construct(
        $reosurce,
        Module $module,
        private Collection $fields,
        private Collection $panels,
        private Collection $viewFilters,
        private array $pickLists,
        private bool $fromReport = false,
        private bool $displayFieldNameOnly = false
    ) {
        parent::__construct($reosurce);

        $this->wrap($module->name);
    }

    public function toArray($request)
    {
        $paginated = $this->resource->toArray();

        return [
            'collection' => [
                'data' => $this->getModelCollection($this->collection),
                'meta' => [
                    'pagination' => [
                        'total' => $paginated['total'] ?? null,
                        'count' => $paginated['per_page'] ?? null,
                        'per_page' => $paginated['per_page'] ?? null,
                        'current_page' => $paginated['current_page'] ?? null,
                        'links' => [
                            'first' => $paginated['first_page_url'] ?? null,
                            'last' => $paginated['last_page_url'] ?? null,
                            'prev' => $paginated['prev_page_url'] ?? null,
                            'next' => $paginated['next_page_url'] ?? null,
                        ],
                    ],
                ],
            ],
            'fields' => new FieldCollection($this->fields),
            'panels' => new PanelCollection($this->panels),
            'viewFilters' => new ViewFilterCollection($this->viewFilters),
        ];
    }

    public function getModelCollection($collection)
    {
        return $collection->transform(function (Base $base) {

            $data = ['_id' => $base->_id];

            $fields = $this->fields;

            $appends = $base->getAppends();

            foreach ($fields as $field) {
                $value = null;

                if ($field->fieldType->name == 'lookupModel') {
                    if ($field->name == 'branch_id' && $base->{$field->name} == 'esco-global' && ! $this->fromReport) {
                        $value = 'esco-global';
                    } elseif ($field->name == 'lastSchedule' && ! $this->fromReport) {
                        $schedID = $base->lastSchedule ?? null;
                        $details = [];

                        if ($schedID) {
                            $details['scheduleCode'] = ServiceSchedule::find($schedID)->scheduleCode ?? null;
                            $details['serviceStartDate'] = ScheduleAssignment::where('service_schedule_id', $schedID)->orderBy('serviceStartDate')->first()->serviceStartDate ?? null;
                            $details['serviceEndDate'] = ScheduleAssignment::where('service_schedule_id', $schedID)->orderBy('serviceEndDate', 'DESC')->first()->serviceEndDate ?? null;
                        }

                        $value = $details;
                    } else {
                        $lookupFieldReturnables = (new FieldService)->getLookupReturnables($field, true, $this->displayFieldNameOnly);

                        $items = $this->getItemReturnables($base, $field, $lookupFieldReturnables);

                        if ($field->relation->method == 'belongsTo' || $field->relation->method == 'hasOne') {
                            $items = $items->first();

                            if ($items) {
                                $items = $items->toArray();
                            }
                        } elseif ($this->fromReport) {
                            $items = $field->relation->entity->getModel()->whereIn('_id', (array) $base->{$field->name})->get($lookupFieldReturnables);
                        }

                        $value = $items;
                    }

                    if ($field->relation->entity->name == 'AddressBook' && ! $this->fromReport) {
                        if (is_array($value) && array_key_exists('country_id', $value) && $value['country_id']) {
                            $value['country_id'] = ['_id' => $value['country_id'], 'name' => Country::find($value['country_id'])->name ?? null];
                        }
                    }

                } elseif ($field->fieldType->name === 'picklist') {
                    if (is_array($base->{$field->name})) {
                        $value = array_values(array_intersect_key($this->pickLists[$field->listName], array_flip($base->{$field->name})));
                    } else {
                        $value = $this->pickLists[$field->listName][$base->{$field->name}] ?? null;
                    }

                    // DI KO LAM SAN TONG CALLER. DI NAMAN TINATAWAG SA V1
                    // if ($this->caller === 'picklist') {
                    //     $value = ['values' => $value, 'picklist' => true];
                    // }

                    if ($field->entity->name == 'BreakdownLog') {
                        $value = ['value' => $value, '_id' => $base->{$field->name}];
                    }
                } elseif ($field->fieldType->name == 'boolean') {
                    $value = $base->{$field->name} ?? false;
                } elseif ($field->rusType) {
                    $value = $base->{$field->name} ?? null;

                    if ($value === null) {
                        $value = \Rus::resolve($base, $field); // TO BE RESOLVED BY CHARISSE
                    }
                } elseif ($field->formulaType) {
                    $value = $base->{$field->name} ?? null;

                    if ($value === null) {
                        $value = FormulaParser::parseField($field, $base, true); // TO BE RESOLVED BY CHARISSE
                    }
                } else {
                    $value = $base->{$field->name};
                }

                $data[$field->name] = $value;
            }

            if (! $this->displayFieldNameOnly && ! empty($appends)) {
                foreach ($appends as $append) {
                    $data[$append] = $base->{$append};
                }
            }

            return $data;
        });
    }

    public function paginationInformation($request, $paginated, $default)
    {
        unset($default['links'], $default['meta']);

        return $default;
    }

    protected function getItemReturnables($model, $field, $returnableFields)
    {
        return $model
            ->dynamicRelationship($field->relation->method, $field->relation->class, $field->relation->foreign_key, $field->relation->local_key, $field->uniqueName, true)
            ->get($returnableFields);
    }
    // public function toResponse($request)
    // {
    //     $data = $this->resolve($request);
    //     if ($data instanceof Collection) {
    //         $data = $data->all();
    //     }

    //     $paginated = $this->resource->toArray();
    //     // perform a dd($paginated) to see how $paginated looks like

    //     $json = array_merge_recursive(
    //         [
    //             self::$wrap => $data
    //         ],
    //         // [
    //         //     'links' => [
    //         //         'first' => $paginated['first_page_url'] ?? null,
    //         //         'last' => $paginated['last_page_url'] ?? null,
    //         //         'prev' => $paginated['prev_page_url'] ?? null,
    //         //         'next' => $paginated['next_page_url'] ?? null,
    //         //     ],
    //         //     'meta' => [
    //         //         'current_page' => $paginated['current_page'] ?? null,
    //         //         'total_items' => $metaData['total'] ?? null,
    //         //         'per_page' => $metaData['per_page'] ?? null,
    //         //         'total_pages' => $metaData['total'] ?? null,
    //         //     ],
    //         // ],
    //         $this->with($request),
    //         $this->additional
    //     );

    //     $status = $this->resource instanceof Base && $this->resource->wasRecentlyCreated ? 201 : 200;

    //     return response()->json($json, $status);
    // }
}
