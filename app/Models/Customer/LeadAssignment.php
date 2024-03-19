<?php

namespace App\Models\Customer;

use App\Models\Company\Branch;
use App\Models\Company\BusinessUnit;
use App\Models\Core\Country;
use App\Models\Model\Base;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LeadAssignment extends Base
{
    protected $entity = 'LeadAssignment';

    protected $collection = 'lead_assignments';

    protected $guarded = [];

    public function scopeNew(Builder $query, $boolean = true)
    {
        return $query->where('is_new', $boolean);
    }

    public function scopeService(Builder $query, $boolean = true)
    {
        return $query->where('is_service', $boolean);
    }

    public function scopeBdm(Builder $query, $boolean = true)
    {
        return $query->where('is_bdm', $boolean);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
