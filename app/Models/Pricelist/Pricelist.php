<?php

namespace App\Models\Pricelist;

use App\Models\Core\Country;
use App\Models\Core\Currency;
use App\Models\Core\File;
use App\Models\Core\Port;
use App\Models\Customer\Account;
use App\Models\Model\Base;
use App\Models\Product\Pricebook;
use App\Models\Product\ProductCategory;
use App\Models\User;

class Pricelist extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'pricelists';

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', '_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', '_id');
    }

    public function port()
    {
        return $this->belongsTo(Port::class, 'port_id', '_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', '_id');
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, null, 'pricelist_ids', 'product_category_ids');
    }

    public function accessBy()
    {
        return $this->belongsToMany(User::class, null, 'pricelist_access_by_ids', 'access_by_ids');
    }

    public function exportBy()
    {
        return $this->belongsToMany(User::class, null, 'pricelist_export_by_ids', 'export_by_ids');
    }

    public function basedPricelist()
    {
        return $this->belongsTo(Pricelist::class, 'based_pricelist_id', '_id');
    }

    public function exworkPrices()
    {
        return $this->hasMany(ExworkPrice::class, 'pricelist_id', '_id');
    }

    public function exworkVersions()
    {
        return $this->hasMany(ExworkVersion::class, 'pricelist_id', '_id');
    }

    public function logs()
    {
        return $this->hasMany(Log::class, 'pricelist_id', '_id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'pricelist_id', '_id');
    }

    public function pricebooks()
    {
        return $this->belongsToMany(Pricebook::class, null, 'pricelist_ids', 'pricebook_ids');
    }
}
