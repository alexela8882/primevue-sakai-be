<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Picklist extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'picklists';

    protected $fillable = [
        'name', 'values', 'catSrcListName',
    ];

    protected $appends = ['_id'];

    protected $hidden = ['listItems'];

    public function listItems()
    {
        return $this->embedsMany(ListItem::class, 'items');
    }

    public function getItemsAttribute()
    {
        return $this->listItems;
    }
}
