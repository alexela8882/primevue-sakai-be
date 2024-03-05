<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ModuleResource;
use App\Models\Module\Module;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser();
    }

    public function index()
    {
        if ($this->moduleDataCollector->user) {
            if ($this->moduleDataCollector->user->roles->contains('name', 'crm_admin')) {
                return ModuleResource::collection(Module::with('entity')->get());
            } else {
                return response()->json([], 200);
            }
        }

        return redirect('/');
    }

    public function getShowRelatedList(Request $request)
    {
        return $this->moduleDataCollector
            ->setModule($request->input('module-name'))
            ->getShowRelatedList($request);
    }
}
