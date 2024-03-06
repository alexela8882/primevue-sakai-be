<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\SalesQuote;
use App\Services\ModuleDataCollector;
use App\Services\SalesModuleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SalesQuotationController extends Controller
{
    use ApiResponseTrait;

    private User $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->user = Auth::guard('api')->user();
        $this->moduleDataCollector->setUser()->setModule('salesquotes');
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->user->canView('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Quote list');
            }

            return $this->moduleDataCollector->getIndex($request);

        });
    }

    public function show(SalesQuote $salesquote, Request $request)
    {
        return $this->respondFriendly(function () use ($salesquote, $request) {
            if (! $this->user->canRead('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Quote records');
            }

            return $this->moduleDataCollector->getShow($salesquote, $request);

        });

    }

    public function store(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->user->canRead('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to create Sales Quote');
            }
            $item = $this->moduleDataCollector->postStore($request);

            return $this->respondSuccessful('Sales opportunity successfully saved', $item->_id);
        });
    }

    public function update(SalesQuote $salesquote, Request $request)
    {
        return $this->respondFriendly(function () use ($salesquote) {

            //$item = $this->moduleDataCollector->patchUpdate($salesquote->_id, $request);

            (new SalesModuleService)->checkQuoteStat($salesquote->_id, true);

            return $this->respondSuccessful('Quotation successfully updated', $salesquote->_id);

        });
    }

    public function upsert($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id) {

            $item = $id; //$this->moduleDataCollector->upsert($id, $request);
            (new SalesModuleService)->checkQuoteStat(SalesQuote::find($id));

            return $this->respond([
                'item' => $item, 'message' => 'Items saved',
            ]);
        });
    }

    public function destroy(SalesQuote $salesquote)
    {
        return $this->respondFriendly(function () use ($salesquote) {
            $salesquote->items()->update(['sales_quote_id', null]);
            $salesquote->delete();

            return $this->respondSuccessful('Sales quotation successfully deleted.');
        });
    }
}
