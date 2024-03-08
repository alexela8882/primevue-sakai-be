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
        $viewFilter->moduleName = $request->moduleName;
        $viewFilter->sortField = $request->sortField;
        $viewFilter->sortOrder = $request->sortOrder ? $request->sortOrder : 'asc';
        $viewFilter->pageSize = $request->pageSize ? $request->pageSize : 10;
        $viewFilter->currentDisplay = 'table';
        $viewFilter->search_fields = $request->_searchFields;
        $viewFilter->fields = $request->fields;
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

        // update current display
        $viewFilter->currentDisplay = $request->updateType;
        if ($request->updateType == 'filters') {

            $viewFilter->filters = $request->filters;

        } elseif ($request->updateType == 'kanban') {

            $viewFilter->summarize_by = $request->summarize_by;
            $viewFilter->group_by = $request->group_by;
            $viewFilter->title_ids = $request->title_ids;

        } elseif ($request->updateType == 'table') {

            if ($request->filterName) {
                $viewFilter->filterName = $request->filterName;
            }
            if ($request->_searchFields) {
                $viewFilter->search_fields = $request->_searchFields;
            }
            if ($request->pickList) {
                $viewFilter->fields = $request->pickList;
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
