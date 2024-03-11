<?php

namespace App\Http\Resources\Report;

use App\Http\Resources\Core\ModelResource;
use App\Models\Core\FolderAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    protected static $user;

    public static function customCollection($resource, $user)
    {
        self::$user = $user;

        return parent::collection($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['folder_id'] = ['_id' => $request->folder_id, 'label' => $request->folder->label];

        $access = FolderAccess::where('folder_id', $request->folder_id)->where('user_id', $this->user)->first();

        if ($access) {
            $data['user_access'] = picklist_value('access_type', [$access->access_type_id])[0];
        } else {
            $data['user_access'] = 'Manage';
        }

        if ($request->created_by) {
            $s3 = User::find($request->created_by);
            $data['created_by'] = ['_id' => $s3->_id, 'firstName' => $s3->firstName, 'lastName' => $s3->lastName, 'fullName' => $s3->fullName];

            $s2 = User::find($request->updated_by);
            $data['updated_by'] = ['_id' => $s2->_id, 'firstName' => $s2->firstName, 'lastName' => $s2->lastName, 'fullName' => $s2->fullName];
        }

        $filters = $request->filter ?? [];
        $newFilters = [];
        foreach ($filters as $key => $filter) {
            $cField = Field::find($filter['field']);
            if ($cField->fieldType->name == 'lookupModel') {
                $cf = Field::where('_id', $filter['field'])->get();
                $dp = $cField->relation->getActualDisplayFields();
                $pls = (new PicklistService)->getPicklistsFromFields($dp);
                $model = $cField->relation->entity->getModel()->whereIn('_id', (array) $filter['value'])->get();
                $item = new ModelResource($model, $dp, $pls);
                $newFilters[] = ['field' => $filters[$key]['field'], 'operator' => $filters[$key]['operator'] ?? $filters[$key]['operation'], 'value' => $item];
            } else {
                $newFilters[] = $filters[$key];
            }
        }
        $data['filter'] = $newFilters;

        return $data;

    }
}
