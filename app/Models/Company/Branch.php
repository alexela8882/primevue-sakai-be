<?php

namespace App\Models\Company;

use App\Models\Core\Country;
use App\Models\Model\Base;
<<<<<<< HEAD

class Branch extends Base
{
    protected $connection = 'mongodb';
  	protected $collection = 'branches';

  public function country()
  {
    return $this->belongsTo(Country::class, 'country_id');
  }
=======
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
>>>>>>> 87617fb (Changes)
}
