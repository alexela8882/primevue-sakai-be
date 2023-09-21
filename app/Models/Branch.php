<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class Branch extends Model
{
  use HasFactory;
  protected $connection = 'mongodb';
  protected $collection = 'branches';

  public function country () {
    return $this->belongsTo(Country::class, 'country_id');
  }
}
