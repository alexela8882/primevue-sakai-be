<?php

namespace App\Models\Report;

use App\Models\Model\Base;

class ReportType extends Base
{
    //

    protected $appends = [
        'main_entity_name',
    ];

    public function folder()
    {
        return $this->belongsTo('App\Models');
    }

    public function entityData()
    {
        return $this->hasMany('App\Models\Report\ReportEntity');
    }

    public function getMainEntityNameAttribute()
    {
        return $this->mainEntity()->name ?? null;
    }

    public function mainEntity()
    {
        return $this->entityData()->orderBy('order', 'ASC')->first();
    }

    public function reportFields()
    {
        return $this->hasMany('App\Models\Report\ReportField');
    }

    public function relatedEntities()
    {
        return $this->belongsToMany('App\Models\Core\Entity', null, 'related_entity_ids');
    }

    public function fieldLabels()
    {
        return $this->embedsMany('App\Models\Report\FieldLabel', 'items');
    }
}
