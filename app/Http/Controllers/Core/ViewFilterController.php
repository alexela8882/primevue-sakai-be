<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ViewFilterResource;
use App\Models\Core\ViewFilter;
use Illuminate\Http\Request;

class ViewFilterController extends Controller
{
    public function index()
    {

        return ViewFilterResource::collection(ViewFilter::where('id', auth()->user()->id)->get());

    }

    public function store(Request $request)
    {

        $userId = $request->default ? 'default' : auth()->user()->_id;

        $this->validate($request, [

            'filterName' => 'required',
            'module_id' => 'required',
            'moduleName' => 'required',
            'sortField' => 'required',
            'sortOrder' => 'required',
            'pageSize' => 'required',
            'queryType' => 'required|in:owned,all',
            'pickList' => 'required|array|min:2',

        ]);

        $viewFilter = new ViewFilter;
        $viewFilter->filterName = $request->filterName;
        $viewFilter->module_id = $request->module_id;
        $viewFilter->moduleName = $request->moduleName;
        $viewFilter->sortField = $request->sortField;
        $viewFilter->sortOrder = $request->sortOrder ? $request->sortOrder : 'asc';
        $viewFilter->pageSize = $request->pageSize ? $request->pageSize : 10;
        $viewFilter->currentDisplay = 'table';
        $viewFilter->search_fields = $request->_searchFields;
        $viewFilter->fields = $request->pickList;
        $viewFilter->owner = $userId;
        $viewFilter->summarize_by = null;
        $viewFilter->group_by = null;
        $viewFilter->queryType = $request->queryType;
        $viewFilter->save();

        if ($viewFilter) {
            return response()->json(['viewFilter' => $viewFilter, 'message' => 'View Filter successfully created.'], 200);
        } else {
            return response()->json(['message' => 'Something went wrong.'], 422);
        }

    }

    public function update(ViewFilter $viewFilter, Request $request)
    {

        // update current display & search fields
        if ($request->updateType !== 'filters') {
            $viewFilter->currentDisplay = $request->updateType;
        }
        if ($request->_searchFields && count($request->_searchFields) > 0) {

            $viewFilters = ViewFilter::where('module_id', $viewFilter->module_id)->get();

            if ($viewFilters) {
                foreach ($viewFilters as $viewFilter) {
                    $viewFilter->update(['search_fields' => $request['_searchFields']]);
                }
            }

        }
        if ($request->updateType === 'filters') {
            // update query type
            if ($request->mode !== 'delete') {
                $viewFilter->queryType = $request->queryType;
                $viewFilter->update();
            }

            if ($request->filters) {
                if ($request->mode === 'new') {
                    $uuid = uniqid(); // generate random id
                    $reconstructedFilter = new \StdClass();
                    $reconstructedFilter->uuid = $uuid;
                    $reconstructedFilter->field_id = $request->filters['field_id'];
                    $reconstructedFilter->operator_id = $request->filters['operator_id'];
                    $reconstructedFilter->values = $request->filters['values'];

                    if (is_array($viewFilter->filters)) {
                        $prevFilters = $viewFilter->filters;
                    } // get previous filters
                    else {
                        $prevFilters = [];
                    }

                    // push new filters
                    array_push($prevFilters, $reconstructedFilter);

                    // return $prevFilters;

                    // return new filters
                    $viewFilter->filters = $prevFilters;
                    $viewFilter->update();

                    $updatedFilter = ViewFilter::where('filters', 'elemMatch', ['uuid' => $reconstructedFilter->uuid])
                        ->project(['filters.$' => true])
                        ->first();

                    $finalFilter = ViewFilterResource::customItemCollection($updatedFilter);

                    $response = [
                        'data' => $finalFilter,
                        'message' => 'New filter successfully added.',
                        'status' => 200,
                    ];

                    return response()->json($response, $response['status']);

                } elseif ($request->mode == 'delete') {

                    $filtersToBeDeleted = $request->filters; // UUID Filters to be deleted

                    $filters = $viewFilter->filters; // Existing filters

                    foreach ($filters as $key => $value) {
                        if (in_array($value['uuid'], $filtersToBeDeleted)) {
                            unset($filters[$key]); // Unset matched UUIDs
                        }
                    }

                    $viewFilter->filters = array_values($filters); // Update filter on the database
                    $viewFilter->save();

                    return response()->json(['data' => $filtersToBeDeleted, 'message' => 'Filter successfully deleted.'], 200);

                } else {
                    // return $request->filters['uuid'];
                    ViewFilter::where('filters.uuid', $request->filters['uuid'])->update(
                        [
                            'filters.$' => $request->filters,
                        ]
                    );

                    $filter = ViewFilter::where('filters', 'elemMatch', ['uuid' => $request->filters['uuid']])
                        ->project(['filters.$' => true])
                        ->first();

                    $finalFilter = ViewFilterResource::customItemCollection($filter);

                    $response = [
                        'data' => $finalFilter,
                        'message' => 'Filter successfully updated.',
                        'status' => 200,
                    ];

                    return response()->json($response, $response['status']);
                }
            } else {
                $response = [
                    'data' => $viewFilter,
                    'message' => 'Filter successfully applied.',
                    'status' => 200,
                ];

                return response()->json($response, $response['status']);
            }

        } elseif ($request->updateType == 'kanban') {

            $viewFilter->summarize_by = $request->summarize_by;
            $viewFilter->group_by = $request->group_by;
            $viewFilter->title_ids = $request->title_ids;

        } elseif ($request->updateType == 'table') {

            if ($request->queryType) {
                $viewFilter->queryType = $request->queryType;
            }
            if ($request->filterName) {
                $viewFilter->filterName = $request->filterName;
            }
            if ($request->pickList && count($request->pickList) > 0) {
                $viewFilter->fields = $request['pickList'];
            }
            if ($request->sortField) {
                $viewFilter->sortField = $request->sortField;
            }
            if ($request->sortOrder) {
                $viewFilter->sortOrder = $request->sortOrder;
            }
            if ($request->pageSize) {
                $viewFilter->pageSize = $request->pageSize;
            }

        }

        $viewFilter->save();

        if ($viewFilter) {
            return response()->json(['viewFilter' => $viewFilter, 'message' => 'View Filter successfully updated.'], 200);
        } else {
            return response()->json(['message' => 'Something went wrong.'], 422);
        }

    }

    public function destroy(ViewFilter $viewFilter, Request $request)
    {

        if ($viewFilter->owner == 'default' || $viewFilter->owner !== auth()->user()->id) {
            return response()->json(['message' => 'You don\'t have permission to delete this view filter.'], 422);
        } elseif ($viewFilter->isDefault == true) {

            if ($request->defaultId) {
                $setAsDefaultViewFilter = ViewFilter::find($request->defaultId);
                if ($setAsDefaultViewFilter) {
                    $setAsDefaultViewFilter->update(['isDefault' => true]);
                    $viewFilter->delete();

                    return response()->json(['viewFilter' => $viewFilter, 'message' => 'View filter has been successfully deleted.'], 200);
                } else {
                    return response()->json(['message' => 'Cannot delete default view filter.'], 422);
                }

            } else {
                return response()->json(['message' => 'Cannot delete default view filter.'], 422);
            }
        } else {
            $viewFilter->delete();

            return response()->json(['viewFilter' => $viewFilter, 'message' => 'View filter has been successfully deleted.'], 200);
        }
    }
}
