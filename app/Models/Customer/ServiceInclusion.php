<?php

namespace App\Models\Customer;

use App\Models\Model\Base;
use App\Models\Service\Service;

class ServiceInclusion extends Base
{
    public function particular()
    {
        return $this->hasMany('App\Models\Customer\ServiceParticularItem');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
