<?php

namespace App\Models\Employee;

use App\Models\Model\Base;

class Employee extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'users';
}
