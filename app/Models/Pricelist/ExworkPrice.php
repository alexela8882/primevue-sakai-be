<?php

namespace App\Models\Pricelist;

use App\Models\Model\Base;
use App\Models\Product\Product;

class ExworkPrice extends Base
{
    protected $collection = 'exwork_prices';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
