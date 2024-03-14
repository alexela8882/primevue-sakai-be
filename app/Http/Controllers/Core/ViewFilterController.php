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
        if ($request->updateType !== 'filters') $viewFilter->currentDisplay = $request->updateType;
        if ($request->_searchFields && count($request->_searchFields) > 0) {

            $viewFilters = ViewFilter::where('module_id', $viewFilter->module_id)->get();

            if ($viewFilters) {
                foreach ($viewFilters as $viewFilter) {
                    $viewFilter->update(['search_fields' => $request['_searchFields']]);
                }
            }

        }
        if ($request->updateType == 'filters') {
            // update query type
            $viewFilter->query_type = $request->query_type;

            // update filters
            // $reconstructedFilters = array(
            //     array(
            //         $request->filters['field_id'],
            //         $request->filters['operator_id'],
            //         $request->filters['values']
            //     )
            // );
            if ($request->mode === 'new') {
                $uuid = uniqid(); // generate random id
                $reconstructedFilters = new \StdClass();
                $reconstructedFilters->uuid = $uuid;
                $reconstructedFilters->field_id = $request->filters['field_id'];
                $reconstructedFilters->operator_id = $request->filters['operator_id'];
                $reconstructedFilters->values = $request->filters['values'];

                $prevFilters = $viewFilter->filters; // get previous filters

                // push new filters
                array_push($prevFilters, $reconstructedFilters);

                // return $prevFilters;

                // return new filters
                $viewFilter->filters = $prevFilters;
                $viewFilter->update();

                $response = [
                    'data' => $reconstructedFilters,
                    'message' => 'New filter successfully added.',
                    'status' => 200
                ];
                return response()->json($response, $response['status']);
            } else {
                // return $request->filters['uuid'];
                ViewFilter::where('filters.uuid', $request->filters['uuid'])->update(
                    [
                        'filters.$' => $request->filters
                    ]
                );

                $filter = ViewFilter::where('filters', 'elemMatch', ['uuid' => $request->filters['uuid']])
                                    ->project(['filters.$' => true])
                                    ->first();
                $response = [
                    'data' => $filter,
                    'message' => 'Filter successfully updated.',
                    'status' => 200
                ];
                return response()->json($response, $response['status']);
            }

        } elseif ($request->updateType == 'kanban') {

            $viewFilter->summarize_by = $request->summarize_by;
            $viewFilter->group_by = $request->group_by;
            $viewFilter->title_ids = $request->title_ids;

        } elseif ($request->updateType == 'table') {

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
        if ($viewFilter) {
            if ($viewFilter->owner == 'default' || $viewFilter->owner !== auth()->user()->id) {
                return response()->json(['message' => 'You don\'t have permission to delete this view filter.'], 422);
            } elseif ($viewFilter->isDefault == true) {
                return response()->json(['message' => 'Cannot delete default view filter.'], 422);
            } else {
                $viewFilter->delete();

                return response()->json(['viewFilter' => $viewFilter, 'message' => 'View filter has been successfully deleted.'], 200);
            }
        } else {
            return response()->json(['message' => 'View filter cannot be found.'], 422);
        }
    }
}
