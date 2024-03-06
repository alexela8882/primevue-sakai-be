<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class State extends Base
{
    protected $entity = 'State';

    protected $collection = 'states';

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
