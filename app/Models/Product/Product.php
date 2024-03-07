<?php

namespace App\Models\Product;

use App\Models\Company\BusinessUnit;
use App\Models\Model\Base;

class Product extends Base
{
    protected $entity = 'Product';

    protected $collection = 'products';

    public function businessUnits()
    {
        return $this->belongsToMany(BusinessUnit::class, null, 'business_unit_ids', 'product_ids');
    }

    public function prices()
    {
        return $this->hasMany(Price::class, 'product_id', '_id');
    }

    public function inclusiveServices()
    {
        return $this->belongsToMany(Service::class, null, 'product_ids', 'inclusive_service_ids');
    }

    public function opportunities()
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, null, 'product_ids', 'category_ids');
    }

    public function groupLinks()
    {
        return $this->hasMany(ProductLinkGroup::class, 'product_id', '_id');
    }

    public function exworkPrices()
    {
        return $this->hasMany(ExworkPrice::class, 'product_id', '_id');
    }
}
