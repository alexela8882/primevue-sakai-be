<?php

namespace App\Models\Report;

use App\Models\Core\Entity;
use App\Models\Model\Base;

class ReportEntity extends Base
{
    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function relatedEntity()
    {
        return $this->belongsTo(Entity::class, 'related_entity_id');
    }
}
