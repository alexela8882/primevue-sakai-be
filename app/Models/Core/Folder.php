<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Folder extends Base
{
    protected $connection = 'mongodb';

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
