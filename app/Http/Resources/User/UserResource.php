<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Company\BranchResource;
use App\Http\Resources\Employee\PositionResource;
use App\Models\Company\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

		$permissions = [];
        $modules = [];

        $v2Branches = [
                       '5b344ae1678f711dfc04ec3a', 
                       '5b344ae1678f711dfc04ec3b', 
                       '5b344ae1678f711dfc04ec3c',
                       '5badf748678f7111186ba275',
                       '5d3a79baa6ebc7d80e760692'
                    ];
		$roles = [];
		foreach ($this->roles as $role) {
            $permissions = array_merge($permissions, $role->permissions->pluck(['name'])->all());

            foreach ($role->permissions->all() as $permission) {
                $moduleName = $permission->module->name;

                if (!in_array($moduleName, $modules))
                    $modules[] = $moduleName;
            }
			array_push($roles, $role->name);
        }

		if(in_array($this->branch_id, $v2Branches)) 
            $modules[] = 'serviceschedulesv2';

        return [
			'_id' => $this->_id,
			'username' => $this->username,
            'email' => $this->email,
            'name' => $this->fullName,
			'gender' => $this->gender,
			'dev_token' => $this->dev_token,
			'isGlobalEngr' => isset($this->isGlobalEngr) ? $this->isGlobalEngr : false,
			// 'session_expiry' => $this->refreshSessionAttr(),
			'avatar' => $this->avatar,
			'roles' => $this->roles->pluck('name'),
			'permissions' => $permissions,
			'modules' => array_merge($modules, ['dashboard']),
			'phoneNo' => $this->phoneNo,
			'timezone_id' => $this->timezone_id,
			'lastLoginAt' => $this->loginHistories()->latest()->first(),
			'active' => $this->active,
			'hasEmployeeAccess' => $this->hasEmployeeAccess,
			'hasLeadAssignmentAccess' => $this->hasLeadAssignmentAccess,
			'employeeNo' => $this->management ? null : $this->employeeNo,
			'dateHired' => $this->management ? null : $this->dateHired,
			'position' => PositionResource::make($this->position),
			'management' => $this->management ? true : false,
			'branch' => BranchResource::make($this->branch),
			'managedBranches' => BranchResource::collection($this->handledBranches),

			
			
			
		];
    }
}
