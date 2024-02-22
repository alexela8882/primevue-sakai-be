<?php

namespace  App\Models\Customer;

use Jenssegers\Mongodb\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;;
use App\Models\Core\Country;
use App\User;

class SalesQuote extends Model
{
    use SoftDeletes;

    protected $date = ['deleted_at'];
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
}
