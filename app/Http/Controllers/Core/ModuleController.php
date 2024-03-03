<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ModuleResource;
use App\Models\Module\Module;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    protected $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->user = auth('api')->user();
    }

    public function index()
    {
        if ($this->user) {

            if ($this->user->roles->contains('name', 'crm_admin')) {
                return ModuleResource::collection(Module::with('entity')->get());
            } else {
                return response()->json([], 200);
            }
        }

        return redirect('/');
    }

    public function getShowRelatedList(string $identifier, Request $request)
    {
        $request->validate([
            'module-name' => 'required|string|exists:modules,name',
        ]);

        $this->moduleDataCollector->setUser()->setModule($request->input('module-name'))->getRelatedList($identifier);
    }
}
