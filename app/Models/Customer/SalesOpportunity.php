<?php

namespace App\Models\Customer;

use App\Models\Company\BusinessUnit;
use App\Models\Model\Base;
use App\Models\User;

class SalesOpportunity extends Base
{
    protected $date = ['deleted_at'];

    protected $collection = 'sales_opportunities';

    protected $entity = 'SalesOpportunity';

    protected $connection = 'mongodb';

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function businessUnits()
    {
        return $this->belongsToMany(BusinessUnit::class);
    }

    public function tasks()
    {
        return $this->hasMany('App\Models\Customer\SalesOpportunity\Task');
    }

    public function events()
    {
        return $this->hasMany('App\Models\Customer\SalesOpportunity\Event');
    }

    public function quotations()
    {
        return $this->hasMany(SalesQuote::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOpptItem::class, 'sales_opportunity_id', '_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function salesPersonInCharge()
    {
        return $this->belongsTo(Contact::class, 'salesperson_in_charge_id');
    }

    public function branch()
    {
        return $this->belongsTo('App\Models\Company\Branch');
    }

    public function lead()
    {
        return $this->belongsTo('App\Models\Customer\Lead');
    }
}
