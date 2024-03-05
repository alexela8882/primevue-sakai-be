<?php

namespace App\Models\Customer;

use App\Models\Model\Base;

class Account extends Base
{
    protected $connection = 'mongodb';

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, null, 'account_ids', 'contact_ids');
    }
}
