<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ModelResource;
use App\Models\Core\Entity;
use App\Models\Core\Log;
use App\Models\Core\Picklist;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        //
    }

    public function index(Request $request)
    {
        $page = $request->get('page', 1);

        $limit = $request->get('limit', 25);

        $skip = ($page - 1) * $limit;

        $query = Log::query()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->where(function ($query) use ($request) {
                foreach ($request->get('queries', []) as $key => $value) {
                    $query->where($key, $value);
                }
            });

        $total = $query->count();

        $last = ceil($total / $limit);

        return [
            'metadata' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => $last,
            ],
            'data' => $query
                ->skip($skip)
                ->take($limit)
                ->get()
                ->map(function ($log) {
                    $entity = Entity::where('_id', $log->entity_id)->first();

                    $fields = $entity->fields;

                    $pickLists = Picklist::getPicklistsFromFields($fields);

                    $model = $entity->getModel()->where('_id', $log->record_id)->first();

                    $log->record_id = new ModelResource($model, $fields, $pickLists);

                    $log->created_by = new ModelResource($log->createdBy, $fields, $pickLists);

                    return $log;
                }),
        ];
    }
}
