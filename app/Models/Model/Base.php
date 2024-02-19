<?php

namespace App\Models\Model;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Base extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $dates = ['deleted_at'];
}
