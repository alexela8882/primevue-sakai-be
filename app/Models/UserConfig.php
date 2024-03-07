<?php

namespace App\Models;

use App\Models\Model\Base;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserConfig extends Base
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'user_configs';
}
