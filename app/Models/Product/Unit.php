<?php

namespace App\Models\Product;

use App\Models\Model\Base;

class Unit extends Base
{
    public function productCategories()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
