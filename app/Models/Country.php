<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model\Base;

class Country extends Base
{
  use HasFactory;

  protected $connection = 'mongodb';
  protected $collection = 'countries';
}
