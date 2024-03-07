<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Section extends Base
{
    protected $connection = 'mongodb';

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }
}
