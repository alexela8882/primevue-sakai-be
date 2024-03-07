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

    public function store(Request $request)
    {
        $campaign = $this->moduleDataCollector->postStore($request);

        return $campaign?->_id;
    }

    public function show(Campaign $campaign, Request $request)
    {
        return $this->moduleDataCollector->getShow($campaign, $request);
    }

    public function update(Campaign $campaign, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($campaign, $request);
    }

    public function patchUpsert(Campaign $campaign, Request $request)
    {
        return $this->moduleDataCollector->patchUpsert($campaign, $request);
    }
}
