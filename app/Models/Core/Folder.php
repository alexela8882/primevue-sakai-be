<?php

namespace App\Models\Core;

use App\Models\Model\Base;
use App\Models\Module\Module;

class Folder extends Base
{
    protected $connection = 'mongodb';

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
