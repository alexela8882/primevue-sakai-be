<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class EntityConnection extends Base
{
    protected $connection = 'mongodb';

    public function entities()
    {
        return $this->belongsToMany(Entity::class, null, 'connection_ids', 'entity_ids');
    }

    public function deepEntities($mutableOnly = false, $depthLimit = null)
    {
        $entities = collect([]);

        if ($mutableOnly) {
            $entityList = $this->entities()->where('mutable', true)->get();
        } else {
            $entityList = $this->entities;
        }

        if ($depthLimit) {
            $depthLimit = $depthLimit - 1;

            if ($depthLimit <= 0) {
                return $entityList;
            }
        }

        foreach ($entityList as $entity) {
            $entities->push($entity);

            if ($entity->entityConnection) {
                $mutables = $entity->entityConnection->deepEntities(true, $depthLimit);
                $entities = $entities->merge($mutables);
            }
        }

        return $entities;
    }
}
