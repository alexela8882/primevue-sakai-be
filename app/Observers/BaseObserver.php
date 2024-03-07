<?php

namespace App\Observers;

use App\Models\Model\Base;

class BaseObserver
{
    public function created(Base $base)
    {
        $base->updateQuietly([
            'oid' => $base->_id,
        ]);
    }
}
