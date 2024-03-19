<?php

namespace App\Models\Static;

use App\Models\Model\Base;

class ActivityLog extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'activity_logs';
}
