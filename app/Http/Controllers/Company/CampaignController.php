<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company\Campaign;
use App\Services\ModuleDataCollector;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    protected $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector = $moduleDataCollector->setUser()->setModule('campaigns');

        $this->user = auth('api')->user();
    }

    public function index(Request $request)
    {
        if ($this->user->canView('campaigns')) {
            return $this->moduleDataCollector->getIndex($request);
        }
    }

    public function show(Campaign $campaign, Request $request)
    {
        return $this->moduleDataCollector->getShow($campaign, $request);
    }
}
