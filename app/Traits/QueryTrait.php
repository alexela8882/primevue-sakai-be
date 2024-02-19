<?php

namespace App\Traits;
use Moloquent\Eloquent\Builder;

trait QueryTrait {

    public function mergeQueries(Builder $builder1, Builder $builder2) {

        $builder1->getQuery()->mergeWheres($builder2->getQuery()->wheres, $builder2->getQuery()->getBindings());

        return $builder1;
    }

}