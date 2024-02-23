<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ModuleResource;
use App\Models\Module\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
	protected $user;
    
	public function __construct()
    {
        $this->user = auth('api')->user();
    }
	
	public function all(){

		if ($this->user) {

			if ($this->user->roles->contains('name', 'crm_admin')) {
				return ModuleResource::collection(Module::with('entity')->get());
			} else {
				return response()->json([],200);
			}

		}
		return redirect('/');
	}
}
