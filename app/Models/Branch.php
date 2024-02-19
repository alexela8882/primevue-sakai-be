<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model\Base;
use App\Models\Core\Country;

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
