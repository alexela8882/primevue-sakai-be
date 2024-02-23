<?php

namespace App\Services;

use App\Models\Core\Relation;

class RelationService
{
    public function getActualDisplayFields(Relation $relation)
    {
        $displayFields = $this->getDisplayFields($relation);

        return $relation->entity->fields->whereIn('name', $displayFields);
    }

    public function getDisplayFields(Relation $relation, bool $includePopUp = true)
    {
        if (isset($relation->popupDisplayFieldName) && $includePopUp === true) {
            return $relation->popupDisplayFieldName;
        } else {
            return $relation->displayFieldName;
        }
    }
}
