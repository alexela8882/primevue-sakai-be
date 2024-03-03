<?php

namespace App\Models\Customer;

use App\Models\Model\Base;

class OpportunityStageLog extends Base
{
    public function opportunity()
    {
        return $this->belongsTo(SalesOpportunity::class, 'sales_opportunity_id', '_id');
    }
}
