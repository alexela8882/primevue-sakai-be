<?php

namespace App\Models\Company;

use App\Models\Core\Country;
use App\Models\Model\Base;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Base
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'branches';

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
