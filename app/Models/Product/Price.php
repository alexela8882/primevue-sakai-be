<?php

namespace App\Models\Product;

use App\Models\Core\Currency;
use App\Models\Core\Log;
use App\Models\Model\Base;

class Price extends Base
{
    protected $entity = 'Price';

    public function product()
    {
        return $this->belongsTo('App\Models\Product\Product');
    }

    public function listPrices()
    {
        return $this->hasMany('App\Models\Customer\SalesOpptItem', 'list_price_id', '_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', '_id');
    }

    public function pricebook()
    {
        return $this->belongsTo(Pricebook::class, 'pricebook_id', '_id');
    }

    public function priceChanges()
    {
        return $this->hasMany(Log::class, 'record_id', '_id');
    }
}
