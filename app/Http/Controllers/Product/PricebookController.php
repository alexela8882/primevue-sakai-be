<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Pricebook;
use App\Services\ModuleDataCollector;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class PricebookController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser()->setModule('pricebooks');
    }

    public function index(Request $request)
    {
        return $this->moduleDataCollector->getIndex($request);
    }

    public function store(Request $request)
    {
        $pricebook = $this->moduleDataCollector->postStore($request);

        return $this->respondSuccessful('Request successful', ['_id' => $pricebook->_id]);
    }

    public function show(Pricebook $pricebook, Request $request)
    {
        return $this->moduleDataCollector->getShow($pricebook, $request);
    }

    public function update(Pricebook $pricebook, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($pricebook, $request);
    }
}
