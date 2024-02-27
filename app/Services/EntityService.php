<?php

namespace App\Services;

use App\Models\Core\Entity;
use App\Models\Core\EntityConnection;
use Illuminate\Database\Eloquent\Collection;

class EntityService
{
    public function getLoadedEntity(Entity $entity): Entity
    {
        return $entity->load([
            'connection',
            'connection.entities',
        ]);
    }

    public function getEntityMutables(Entity $entity)
    {
        $entity = $this->getLoadedEntity($entity);

        if ($entity->connection instanceof EntityConnection) {
            return $entity->connection->entities->where(fn (Entity $entity) => $entity->mutable === true);
        }

        return collect();
    }

    public function hasMutable(Entity $entity): bool
    {
        return $this->getEntityMutables($entity)->isNotEmpty();
    }

    public function deepConnectedEntities(Entity $entity, $isMutableOnly = false, $depthLimit = null)
    {
        $entity = $this->getLoadedEntity($entity);

        if ($entity->connection instanceof EntityConnection) {
            $entities = $entity->connection->entities;

            if ($isMutableOnly) {
                $entities = $entities->where('mutable', true);
            }

            if ($depthLimit) {
                $depthLimit = $depthLimit - 1;

                if ($depthLimit <= 0) {
                    return $entities;
                }
            }

            foreach ($entities as $entity) {
                
            }
        }

        return collect();
    }

    public function deleteMutableChanges()
    {

    }
}