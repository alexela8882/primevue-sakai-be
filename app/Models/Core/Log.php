<?php

namespace App\Models\Core;

use App\Models\Model\Base;
use App\Models\User;

class Log extends Base
{
    protected $collection = 'logs';

    protected $attributes = [
        // Christia Lagamson's user identifier
        // This default created_by is used whenever we manually mass update pricebook's prices that are not directly updated in the system
        'created_by' => '5bb104ed678f71061f645215',
    ];

    const UPDATED_AT = null;

    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }
}
