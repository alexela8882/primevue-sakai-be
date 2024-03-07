<?php

namespace App\Models\Customer;

use App\Models\Model\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpportunityStageHistory extends Base
{
    use SoftDeletes;

    protected $date = ['deleted_at'];
}
