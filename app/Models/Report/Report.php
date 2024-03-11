<?php

namespace App\Models\Report;

use App\Models\Core\Folder;
use App\Models\Model\Base;

class Report extends Base
{
    protected $entity = 'Report';

    public function folder()
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }
}
