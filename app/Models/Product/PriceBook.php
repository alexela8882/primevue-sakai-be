<?php

namespace App\Models\Product;

use App\Models\Core\Currency;
use App\Models\Model\Base;
use App\Models\Pricelist\Pricelist;
use App\User;

class Pricebook extends Base
{
    protected $entity = 'Pricebook';

    protected $linkedEntities = ['items' => 'App\Models\Product\Price'];

    public function scopeStandard($query)
    {
        return $query->where('name', 'Standard Price Book');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, null, 'pricebook_ids', 'assignee_ids');
    }

    public function myCurrencies() // I put myCurrencies since currencies is a field name of Pricebook
    {
        return $this->belongsToMany(Currency::class, null, 'pricebook_ids', 'currencies');
    }

    public function prices()
    {
        return $this->hasMany(Price::class, 'pricebook_id', '_id');
    }

    public function pricelists()
    {
        return $this->belongsToMany(Pricelist::class, null, 'pricebook_ids', 'pricelist_ids');
    }
}
