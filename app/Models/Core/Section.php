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

    public function firstColumn()
    {
        return $this->belongsToMany('App\Models\Core\Field', null, 'section1_ids', 'first_ids');
    }

    public function secondColumn()
    {
        return $this->belongsToMany('App\Models\Core\Field', null, 'section2_ids', 'second_ids');
    }
}
