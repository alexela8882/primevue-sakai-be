<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\SalesQuote;
use App\Services\SalesModuleService;
use App\Services\ModuleDataCollector;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesQuotationController extends Controller
{
    use ApiResponseTrait;

    private $mdc;

    private $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->user = Auth::guard('api')->user();
        $this->mdc = $moduleDataCollector->setUser()->setModule('salesquotes');
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->user->canView('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Quote list');
            }

            return $this->mdc->getIndex($request);

        });
    }

    public function show(SalesQuote $salesquote, Request $request)
    {
        return $this->respondFriendly(function () use ($salesquote, $request) {
            if (! $this->user->canRead('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Quote records');
            }

            return $this->mdc->getShow($salesquote, $request);

        });

    }

    public function store(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->user->canRead('salesquotes')) {
                $this->respondUnprocessable('Error. You do not have access to create Sales Quote');
            }
            $item = $this->mdc->postStore($request);

            return $this->respondSuccessful('Sales opportunity successfully saved', $item->_id);
        });
    }

    public function update(SalesQuote $salesquote, Request $request)
    {
        return $this->respondFriendly(function () use ($salesquote) {

            //$item = $this->mdc->patchUpdate($salesquote->_id, $request);

            (new SalesModuleService)->checkQuoteStat($salesquote->_id, true);

            return $this->respondSuccessful('Quotation successfully updated', $salesquote->_id);

        });
    }

    public function upsert($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id) {

            $item = $id; //$this->mdc->upsert($id, $request);
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
