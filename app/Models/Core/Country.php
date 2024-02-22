<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Country extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'countries';
}
