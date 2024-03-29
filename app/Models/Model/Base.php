<?php

namespace App\Models\Model;

use App\Observers\BaseObserver;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Base extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $dates = ['deleted_at'];

    protected $guarded = ['_id', 'oid'];

    protected static $relationship_method;

    protected static $relationship_params = [];

    protected static $loadedRelationClass = [];

    protected static function booted()
    {
        static::observe(BaseObserver::class);
    }

    public function dynamicRelationship($method, $class, $foreignKey = null, $otherKey = null, $relationName = null, $fetchQuery = false)
    {
        static::$relationship_method = $method;

        static::$relationship_params = [];

        static::$relationship_params[] = $class;

        // if relationship method is belongsToMany, pivot table is null
        if ($method == 'belongsToMany') {
            static::$relationship_params[] = null;
        }

        if ($foreignKey) {
            static::$relationship_params[] = $foreignKey;
            if ($otherKey) {
                static::$relationship_params[] = $otherKey;
            }
        }

        if ($relationName) {
            static::$relationship_params[] = $relationName;
        }

        if (! $fetchQuery) {
            return $this->relatedTo;
        } else {
            return $this->relatedTo();
        }
    }

    public function relatedTo()
    {
        return call_user_func_array([$this, static::$relationship_method], static::$relationship_params);
    }

    public function getEntityName()
    {
        return $this->entity ?? (new \ReflectionClass($this))->getShortName();
    }
}
