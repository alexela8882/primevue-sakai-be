<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Picklist extends Base
{
    protected $connection = 'mongodb';

    public function listItems()
    {
        return $this->embedsMany(ListItem::class, 'items');
    }
}
