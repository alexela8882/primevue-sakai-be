<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class ViewFilter extends Base
{
    protected $connection = 'mongodb';

    public function filterQuery()
    {
        return $this->belongsTo('App\Models\Core\ModuleQuery', 'query_id', '_id');
    }
}
