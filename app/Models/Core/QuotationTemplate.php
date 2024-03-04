<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class QuotationTemplate extends Base
{
    protected $guarded = ['_id'];

    public function panels()
    {
        return $this->hasMany('App\Models\Core\QTPanel');
    }
}
