<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model\Base;

class UserConfig extends Base
{
  use HasFactory;

  protected $connection = 'mongodb';
  protected $collection = 'user_configs';
}
