<?php

namespace App\Services;

use App\Models\Core\Entity;
use App\Models\Core\EntityConnection;

class EntityService
{
    public function getEntityMutables(Entity $entity)
    {
        $entity->load([
            'connection',
            'connection.entities',
        ]);

        if ($entity->connection instanceof EntityConnection) {
            return $entity->connection->entities->where(fn (Entity $entity) => $entity->mutable === true);
        }

        return collect();
    }

    public function hasMutable(Entity $entity): bool
    {
        return $this->getEntityMutables($entity)->isNotEmpty();
    }

    public function getLookUpFields($main, $otherEntity)
    {
        $mainID = Entity::where('name', $main)->first()->_id;

        return Entity::where('name', $otherEntity)->first()->fields
            ->load('fieldType')->where('fieldType.name', 'lookupModel')
            ->load('relation')->where('relation.entity_id', $mainID);
    }
}
