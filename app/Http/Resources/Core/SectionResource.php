<?php

namespace App\Http\Resources\Core;

use App\Models\Core\Field;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    private static bool $isMutable;

    private static mixed $identifiers;

    public static function customCollection($resource, bool $isMutable = false, mixed $identifiers = null)
    {
        self::$isMutable = $isMutable;

        self::$identifiers = $identifiers;

        return parent::collection($resource);
    }

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->columnCount) {
            $data['columnCount'] = $this->columnCount;
        }

        if (self::$isMutable) {
            //loop to get the proper order of fields. Will reconstruct panel builder for better solution
            if (self::$identifiers) {
                $fields = array_intersect($this->first_ids, self::$identifiers);

                foreach ($fields as $field) {
                    $data['field_ids'][0][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                }

                if ($this->second_ids) {
                    $fields = array_intersect($this->second_ids, self::$identifiers);

                    foreach ($fields as $field) {
                        $data['field_ids'][1][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                    }
                }

                if ($this->third_ids) {
                    $fields = array_intersect($this->third_ids, self::$identifiers);

                    foreach ($fields as $field) {
                        $data['field_ids'][2][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                    }
                }
            } else {
                foreach ($this->first_ids as $field) {
                    $data['field_ids'][0][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                }

                if ($this->second_ids) {
                    foreach ($this->second_ids as $field) {
                        $data['field_ids'][1][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                    }
                }

                if ($this->third_ids) {
                    foreach ($this->third_ids as $field) {
                        $data['field_ids'][2][] = Field::find($field)->load(['fieldType', 'relation'])->toArray();
                    }
                }
            }
        } else {
            $data['field_ids'][] = $this->first_ids;

            if ($this->second_ids) {
                $data['field_ids'][] = $this->second_ids;
            }
        }

        return $data;
    }
}
