<?php

namespace App\Models\Customer;

use App\Models\Company\Branch;
use App\Models\Core\Currency;
use App\Models\Model\Base;
use App\Models\Product\Product;

class SalesOpptItem extends Base
{
    protected $collection = 'sales_oppt_items';

    protected $entity = 'SalesOpptItem';

    public function opportunity()
    {
        return $this->belongsTo(SalesOpportunity::class, 'sales_opportunity_id', '_id');
    }

    public function quotation()
    {
        return $this->belongsTo(SalesQuote::class, 'sales_quote_id', '_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', '_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', '_id');
    }

    public function discountRequests()
    {
        return $this->hasMany(SalesOpptItemDiscountRequest::class, 'item_id', '_id');
    }

    public function inclusiveServices()
    {
        return $this->belongsToMany('App\Models\Service\Service', null, 'sales_oppt_item_ids', 'inclusive_service_ids');
    }
}
