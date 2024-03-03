<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class QTPanel extends Base
{
    protected $guarded = ['_id'];

    public function quotationTemplate()
    {
        return $this->belongsTo('App\Models\Core\QuotationTemplate');
    }
}
