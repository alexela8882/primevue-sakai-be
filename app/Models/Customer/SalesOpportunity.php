<?php

namespace App\Models\Customer;


use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Model\Base;

class SalesOpportunity extends Base
{
    use SoftDeletes;

    protected $date = ['deleted_at'];
    protected $collection = 'sales_opportunities';
    protected $entity = 'SalesOpportunity';
    protected $connection = 'mongodb';

    // public function account()
    // {
    //     return $this->belongsTo('App\Models\Customer\Account');
    // }

    // public function businessUnits()
    // {
    //     return $this->belongsToMany('App\Models\Company\BusinessUnit');
    // }

    // public function products()
    // {
    //     return $this->hasMany('App\Models\Product\Product');
    // }

    // public function assignees()
    // {
    //     return $this->hasMany('App\User');
    // }

    // public function tasks()
    // {
    //     return $this->hasMany('App\Models\Customer\SalesOpportunity\Task');
    // }

    // public function events()
    // {
    //     return $this->hasMany('App\Models\Customer\SalesOpportunity\Event');
    // }

    // public function quotations()
    // {
    //     return $this->hasMany('App\Models\Customer\SalesQuote');
    // }

    // public function owner()
    // {
    //     return $this->belongsTo('App\User', 'owner_id');
    // }

    // public function salesPersonInCharge()
    // {
    //     return $this->belongsTo('App\Models\Customer\Contact', 'salesperson_in_charge_id');
    // }

    // public function branch()
    // {
    //     return $this->belongsTo('App\Models\Company\Branch');
    // }

    // public function opportunityStageHistories()
    // {
    //     return $this->hasMany('App\Models\Customer\OpportunityStageHistory', 'sales_opportunity_id', '_id');
    // }

    // public function lead()
    // {
    //     return $this->belongsTo(Lead::class);
    // }
}
