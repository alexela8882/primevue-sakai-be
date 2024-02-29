<?php

namespace App\Models\Core;

use App\Models\Model\Base;
use App\Models\Module\Module;
use Illuminate\Support\Facades\App;

class Entity extends Base
{
    protected $connection = 'mongodb';

    public function fields()
    {
        return $this->hasMany(Field::class, 'entity_id', '_id');
    }

    public function panels()
    {
        return $this->hasMany(Panel::class, 'entity_id', '_id');
    }

    public function mainModule()
    {
        return $this->hasOne(Module::class, 'mainEntity', '_id');
    }

    public function connection()
    {
        return $this->hasOne(EntityConnection::class, 'src_entity_id', '_id');
    }

    public function getModel()
    {
        return App::make($this->attributes['model_class']);
    }
}
