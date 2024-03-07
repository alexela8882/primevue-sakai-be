<?php

namespace App\Traits;

use App\Facades\PublicGroup;

trait PublicGroupTrait
{
    public function getPublicGroupMembers($fields, $user, $key = 'owner_id')
    {

        if ($fields === true || $fields->where('name', $key)->first()) {
            // Currently, there is only one type of member: user
            $grps = PublicGroup::getModel()->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->_id);
            })->get();

            return $grps->pluck('members')->flatten()->pluck('user_id')->toArray();

            //            $builder->orWhere(function($query) use ($memberIds, $key){
            //                $query->whereIn($key, $memberIds);
            //            });

        }

        return [];
    }
}
