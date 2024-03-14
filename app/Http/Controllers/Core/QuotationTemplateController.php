<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\QuotationTemplate;
use App\Models\Customer\Account;
use App\Models\Customer\Contact;
use App\Models\Customer\SalesOpportunity;
use App\Models\Customer\SalesOpptItem;
use App\Models\Customer\SalesQuote;
use App\Models\Product\Product;
use App\Services\ModuleDataCollector;
use App\Services\SalesModuleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class QuotationTemplateController extends Controller
{
    use ApiResponseTrait;

    private $user;

    public function __construct(private ModuleDataCollector $mdc)
    {
        $this->user = auth('api')->user();
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            $qtFor = $request->input('qtFor', 'sales');

            return QuotationTemplate::where('qtFor', $qtFor)
                ->whereIn('branch_id', $this->user->handled_branch_ids)
                ->get();

        });
    }

    public function show(QuotationTemplate $quotationtemplate)
    {
        return (new SalesModuleService)->transform($quotationtemplate);
    }

    public function getInfo($id, Request $request)
    {
        //  return $this->respondFriendly(function () use ($id, $request) {
        $return = [];

        $quote = SalesQuote::find($id);
        $opp = SalesOpportunity::find($quote->sales_opportunity_id);

        $productIDs = SalesOpptItem::where('sales_opportunity_id', $opp->_id)->pluck('product_id')->toArray();
        $productIDs = array_unique($productIDs);

        $account = Account::find($opp->account_id);

        $return['Account'] = $this->mdc->setModule('accounts')->setFields()->getShow($account, $request, true)
            ->toResponse(app('request'))
            ->getData()->data ?? null;

        //  $return['Contact'] = $this->mdc->setModule('contacts')->getShow(Contact::find($opp->contact_id), $request, true);

        if ($productIDs) {
            $products = Product::whereIn('_id', $productIDs)->get(['_id', 'uom', 'name', 'description', 'modelCode', 'itemCode']);
            $return['Product']['collection'] = $products;
        }

        $return['Opportunity'] = $this->mdc->setModule('salesopportunities')->getShow($opp, $request, true);
        //   $return['Opportunity']['connected'] = $this->mdc->setModule('salesquotes')->getShow($quote, $request, false, true)['connected'] ?? [];

        //   dd($return);
        return $return;
        //     });
    }
}
