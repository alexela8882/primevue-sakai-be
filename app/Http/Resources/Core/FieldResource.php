<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\ModelCollection;
use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Core\Rule;
use App\Models\Product\Category;
use App\Services\FieldService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

class FieldResource extends JsonResource
{
    protected static $fields;

    protected static $pickLists;

    public static function information($fields, $pickLists, $wrap = 'data')
    {
        static::$fields = $fields;

        static::$pickLists = $pickLists;

        static::$wrap = $wrap;
    }

    public function toArray(Request $request): array
    {
        parent::$wrap = $this->wrap;

        $data = parent::toArray($request);

        $fieldService = new FieldService;

        $defaultValueRule = $this->rules->firstWhere('name', 'default_value');

        if ($this->fieldType->name === 'lookupModel' && $defaultValueRule instanceof Rule) {
            $entity = $this->entity;

            $lookupFieldReturnables = $fieldService->getLookupReturnables($this);

            $entityFields = $entity->fields->whereIn('name', $lookupFieldReturnables);

            $entityModel = App::make($entity->model_class);

            if ($fieldService->hasMultipleValues($this)) {
                $modelCollection = $entityModel->whereIn('_id', [$defaultValueRule->value])->get();

                $data['defaultValue'] = new ModelCollection($modelCollection, $entityFields, self::$pickLists);
            } else {
                $model = $entityModel->where('_id', $defaultValueRule->value)->first();

                $data['defaultValue'] = new ModelCollection($model, $entityFields, self::$pickLists);
            }
        } elseif ($this->fieldType->name === 'rollUpSummary') {
            $aggregateField = Entity::query()
                ->where('name', $this->rusEntity)
                ->with([
                    'fields' => fn ($query) => $this->aggregateField,
                ])
                ->first()
                ->fields
                ->first();

            if ($aggregateField instanceof Field) {
                $data['aggregateField'] = ['name' => $this->aggregateField, 'fieldType' => $aggregateField->fieldType];
            }
        }

        $data['rules'] = $this->rules->pluck(['name', 'value', 'event']);

        // $data['testGroupName'] = $this->testGroupName ?? null; // WHAT IS THIS? @cha

        if (empty($this->category_ids)) {
            $data['category_ids'] = [];
        } else {
            $productCategories = Category::query()
                ->whereIn('_id', $this->category_ids)
                ->select([
                    'name',
                ])
                ->get();

            $data['category_ids'] = $productCategories;
        }

        return $data;
    }
}
