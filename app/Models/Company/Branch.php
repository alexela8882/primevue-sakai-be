<?php

namespace App\Models\Company;

use App\Models\Core\Country;
use App\Models\Model\Base;

class Branch extends Base
{
    protected $connection = 'mongodb';
  	protected $collection = 'branches';

  public function country()
  {
    return $this->belongsTo(Country::class, 'country_id');
  }
}
