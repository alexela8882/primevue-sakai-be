<?php

namespace App\Models\Employee;

use App\Models\Model\Base;

class Position extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'positions';
}
