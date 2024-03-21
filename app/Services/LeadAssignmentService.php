<?php

namespace App\Services;

use App\Models\Customer\LeadAssignment;
use App\Models\User;

class LeadAssignmentService
{
    public function evaluation($lead)
    {
        if ($lead->inquiry_type_id == '61445d13a6ebc793a30e4dd6') {
            $leadAssignments = LeadAssignment::new()
                ->service()
                ->where('country_id', $lead->country_id)
                ->with('user')
                ->get();

            if ($leadAssignments->isEmpty()) {
                $leadAssignments = LeadAssignment::new()
                    ->where('is_bdm', true)
                    ->where('business_unit_id', '5b344ae1678f711dfc04ec3f')
                    ->with('user')
                    ->get()
                    ->filter(function ($leadAssignment) use ($lead) {
                        return in_array($lead->country_id, $leadAssignment->country_ids);
                    });
            }
        } else {
            $leadAssignments = LeadAssignment::new()
                ->service(false)
                ->with('user')
                ->get()
                ->filter(function ($leadAssignment) use ($lead) {
                    return in_array($lead->country->_id, $leadAssignment->country_ids);
                })
                ->filter(function ($leadAssignment) use ($lead) {
                    return in_array($leadAssignment->business_unit_id, $lead->business_unit_ids ?? []);
                });
        }

        $owner = null;
        $bdm = collect([]);
        $alternativeOwners = [];

        if ($leadAssignments->isNotEmpty()) {
            if ($lead->inquiry_type_id == '61445d13a6ebc793a30e4dd6') {
                foreach ($leadAssignments as $leadAssignment) {
                    if ($owner === null) {
                        $owner = $leadAssignment->user_id;
                    } else {
                        $alternativeOwners[] = $leadAssignment->user_id;
                    }
                }
            } else {
                foreach ($leadAssignments as $leadAssignment) {
                    if ($leadAssignment->is_bdm === true && ! $bdm->contains('email', $leadAssignment->user->email)) {
                        $bdm->push($leadAssignment->user);
                    } elseif ($leadAssignment->is_bdm === false && $owner === null) {
                        $owner = $leadAssignment->user_id;
                    } else {
                        $alternativeOwners[] = $leadAssignment->user_id;
                    }
                }
            }

            if ($owner !== null) {
                $user = User::where('_id', $owner)->first();
                $lead->update(['owner_id' => $owner]);

                // if ($alternativeOwners)
                //     $lead->emailRecipients()->attach($alternativeOwners);
            } else {
                $user = User::where('email', 'christia.l@escolifesciences.com')->first();
                $lead->update(['owner_id' => $user->_id]);
                //$this->sendNoLeadOwner($lead);
            }

            // if ($bdm->isEmpty() && $lead->inquiry_type_id != '61445d13a6ebc793a30e4dd6')
            //     $this->sendNoBDMFound($lead);
        } else {
            $user = User::where('email', 'christia.l@escolifesciences.com')->first();
            $lead->update(['owner_id' => $user->_id]);
            //$this->sendNoLeadAssignment($lead);
        }

        return [$user, $bdm];
    }
}
