<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Jobs\Pricebook\ApplyComputePriceJob;
use App\Jobs\Pricebook\CancelComputePriceJob;
use App\Jobs\Pricebook\ComputePriceJob;
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

        return $this->respondSuccessful('Request successful', $pricebook->_id);
    }

    public function show(Pricebook $pricebook, Request $request)
    {
        return $this->moduleDataCollector->getShow($pricebook, $request);
    }

    public function update(Pricebook $pricebook, Request $request)
    {
        return $this->moduleDataCollector->patchUpdate($pricebook, $request);
    }

    public function patchAddPricelist(Pricebook $pricebook, Request $request)
    {
        if ($pricebook->isComputingPrice !== true) {
            if ($request->filled('pricelist-ids')) {
                $pricebook->pricelists()->sync($request->input('pricelist_ids'));

                return $this->respondSuccessful();
            }

            return $this->respondUnprocessable('Rejected. Missing \'pricelist-ids\' field in payload.');
        }

        return $this->respondUnprocessable('Rejected. Pricebook has an ongoing computing prices. Cannot add new \'pricelists\' to this pricebook.');
    }

    public function patchAddFormula(Pricebook $pricebook, Request $request)
    {
        if ($pricebook->isComputingPrice !== true) {
            if ($request->filled('formula')) {
                $pricebook->update(['formula' => $request->input('formula')]);

                return $this->respondSuccessful();
            }

            return $this->respondUnprocessable('Rejected. Missing \'formula\' field in payload.');
        }

        return $this->respondUnprocessable('Rejected. Pricebook has an ongoing computing prices. Cannot add new \'formula\' to this pricebook.');
    }

    public function postComputePrice(Pricebook $pricebook)
    {
        if ($pricebook->isComputingPrice !== true) {
            ComputePriceJob::dispatch($pricebook, $this->moduleDataCollector->user);

            return $this->respondSuccessful();
        }

        return $this->respondSuccessful('Rejected. Pricebook has an on-going computing prices.');
    }

    public function postApplyComputePrice(Pricebook $pricebook)
    {
        if ($pricebook->isComputingPrice === true) {
            ApplyComputePriceJob::dispatch($pricebook, $this->moduleDataCollector->user);

            return $this->respondSuccessful();
        }

        return $this->respondSuccessful('There are no computing prices to apply.');
    }

    public function postCancelComputePrice(Pricebook $pricebook)
    {
        if ($pricebook->isComputingPrice === true) {
            CancelComputePriceJob::dispatch($pricebook, $this->moduleDataCollector->user);

            return $this->respondSuccessful();
        }

        return $this->respondSuccessful('There are no computing prices to cancel.');
    }
}
