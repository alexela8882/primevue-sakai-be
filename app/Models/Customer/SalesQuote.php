<?php

namespace App\Models\Customer;

use App\Models\Core\Country;
use App\Models\Model\Base;
use App\Models\User;

class SalesQuote extends Base
{
    protected $entity = 'SalesQuote';

    protected $collection = 'sales_quotes';

    public function billingCountry()
    {
        return $this->belongsTo(Country::class, 'billing_country_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', '_id');
    }

    public function salesPersonInCharge()
    {
        return $this->belongsTo(Contact::class, 'salesperson_in_charge_id', '_id');
    }

    public function items()
    {
        return $this->hasMany('App\Models\Customer\SalesOpptItem', 'sales_quote_id', '_id');
    }
}
