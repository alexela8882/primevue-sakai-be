<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModelCollection;
use App\Models\Customer\Account;
use App\Models\Customer\Lead;
use App\Models\Customer\OpportunityStageHistory;
use App\Models\Customer\SalesOpportunity;
use App\Models\Customer\SalesOpptItem;
use App\Models\Customer\SalesQuote;
use App\Models\Service\ServiceJob;
use App\Services\ModuleDataCollector;
use App\Services\PicklistService;
use App\Services\SalesModuleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesOpportunityController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser()->setModule('salesopportunities');
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->moduleDataCollector->user->canView('salesopportunities')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Opportunity list');
            }

            return $this->moduleDataCollector->getIndex($request);
        });
    }

    public function show(SalesOpportunity $salesopportunity, Request $request)
    {
        return $this->respondFriendly(function () use ($salesopportunity, $request) {
            if (! $this->moduleDataCollector->user->canRead('salesopportunities')) {
                $this->respondUnprocessable('Error. You do not have access to view Sales Opportunity records');
            }

            return $this->moduleDataCollector->getShow($salesopportunity, $request);
        });
    }

    public function store(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            if (! $this->moduleDataCollector->user->canRead('salesopportunities')) {
                $this->respondUnprocessable('Error. You do not have access to create Sales Opportunity');
            }

            $item = $this->moduleDataCollector->postStore($request);
            $data = (new SalesModuleService)->getStageHistoryData($item->_id);

            if ($data) {
                OpportunityStageHistory::create($data);
            }

            $item->update(['lastStageUpdateDate' => $item->created_at->format('Y-m-d'), 'created_stage_id' => $item->stage_id]);

            return $this->respondSuccessful('Sales opportunity successfully saved', $item->_id);
        });
    }

    public function update(SalesOpportunity $salesopportunity, Request $request)
    {
        return $this->respondFriendly(function () use ($salesopportunity, $request) {
            $stageId = $request->get('stage_id');

            $oldStageID = $salesopportunity->stage_id;

            $updatedSalesopportunity = $this->moduleDataCollector->patchUpdate($salesopportunity->_id, $request);

            $data = (new SalesModuleService)->getStageHistoryData($salesopportunity->_id, false, $salesopportunity);

            if ($data) {
                OpportunityStageHistory::create($data);
            }

            if ($stageId != $oldStageID) {
                $salesopportunity->update(['lastStageUpdateDate' => date('Y-m-d')]);
            }

            if ($request->has('PONo') && $salesopportunity->PONo ?? $request->input('PONo') !== null) {
                ServiceJob::where('sales_opportunity_id', $salesopportunity->_id)->update(['PONo' => $request->input('PONo')]);
            }

            return $this->respondSuccessful('Sales opportunity successfully updated', $updatedSalesopportunity->_id);
        });
    }

    public function upsert($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {
            $item = $this->moduleDataCollector->patchUpsert($id, $request);

            (new SalesModuleService)->checkOpportunityQuoteStat($id);

            return $this->respond([
                'item' => $item->_id, 'message' => 'Items saved',
            ]);
        });
    }

    public function destroy(SalesOpportunity $salesOpportunity)
    {
        return $this->respondFriendly(function () use ($salesOpportunity) {

            SalesQuote::where('sales_opportunity_id', $salesOpportunity->_id)->delete();
            $salesOpportunity->items()->delete();

            OpportunityStageHistory::getModel()->where('sales_opportunity_id', $salesOpportunity->_id)->delete();
            $salesOpportunity->delete();

            return $this->respondSuccessful('Sales opportunity successfully deleted.');

        });
    }

    public function convert($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {
            $lead = Lead::find($id);
            if (! $lead) {
                $this->respondUnprocessable('Error. Unknown lead id '.$id);
            }

            $request['lead_id'] = $id;
            $request['dateConverted'] = new Carbon('NOW');

            $item = $this->moduleDataCollector->postStore($request);

            $data = (new SalesModuleService)->getStageHistoryData($item->_id);

            if ($data) {
                OpportunityStageHistory::create($data);
            }

            SalesOpportunity::find($item->_id)->update(['lastStageUpdateDate' => $item->created_at->format('Y-m-d'), 'created_stage_id' => $item->stage_id]);

            $lead->update([
                'converted' => true,
                'status_id' => picklist_id('lead_status', 'Converted to Opportunity'),
                'dateConverted' => Carbon::now(),
            ]);

            return $this->respondSuccessful('Lead successfuly converted', $item);

        });
    }

    public function getItemsWithSJ($id)
    {
        return $this->respondFriendly(function () use ($id) {
            $opportunity = SalesOpportunity::find($id);
            if ($opportunity) {
                if ($opportunity->generatedSJ ?? false) {
                    $sjs = ServiceJob::where('sales_opportunity_id', $id)->get();

                    return ['data' => $sjs];

                }

                return ['data' => null];
            }

            return $this->respondUnprocessable('Invalid ID.');
        });
    }

    public function getActiveItems($id, Request $request)
    {
        return $this->respondFriendly(function () use ($id, $request) {
            $opportunity = SalesOpportunity::find($id);
            if ($opportunity) {

                $coll = (new SalesModuleService)->getActiveOppItems($id, $request->input('withQuote', false));

                $fields = SalesOpptItem::getEntityFields();
                $esPicklists = PicklistService::getPicklistsFromFields($fields);

                return new ModelCollection($coll, $fields, $esPicklists);

            }

            return $this->respondUnprocessable('Invalid ID.');
        });
    }

    public function checkDetails($oppID)
    {

        $return = null;
        $opportunity = SalesOpportunity::find($oppID);

        if ($opportunity) {

            $activeOppItem = picklist_id('oppt_item_status', 'Active');

            $checkquote = SalesQuote::where('sales_opportunity_id', $opportunity->_id)->count();
            $return['hasQuote'] = $checkquote ? true : false;

            if ($checkquote) {
                $item = $opportunity->items()->where('sales_quote_id', '!=', null)->where('status', $activeOppItem)->count();

                if ($item) {
                    $return['hasActiveQuote'] = true;
                } else {
                    $return['hasActiveQuote'] = false;
                }
            } else {
                $return['hasActiveQuote'] = false;
            }

            return $return;

        }

        return ['message' => 'Invalid opportunity ID'];

    }

    public function getAccountIds($id)
    {
        $quotes = SalesQuote::where('sales_opportunity_id', $id)->get();
        $accounts = [];
        foreach ($quotes as $key => $quote) {
            if ($quote->ship_to_name_id) {
                $acc = Account::find($quote->ship_to_name_id);
                $accounts[] = ['_id' => $acc->_id, 'name' => $acc->name, 'isEscoBranch' => $acc->isEscoBranch, 'quote_id' => $quote->_id];
            }

        }

        return $accounts;
    }

    public function transferOpportunity($opptID, Request $request)
    {

        return $this->respondFriendly(function () use ($opptID, $request) {
            $newAccountID = $request->input('account_id');
            $salesOpportunity = SalesOpportunity::find($opptID);

            if (! $salesOpportunity) {
                return $this->respondUnprocessable('Invalid Opportunity ID.');
            }

            $acc = Account::find($newAccountID);
            if (! $acc) {
                return $this->respondUnprocessable('Invalid Account ID.');
            }

            $odata = [
                'account_id' => $newAccountID,
                'old_account_id' => $salesOpportunity->account_id,
            ];

            $salesOpportunity->update($odata);

            SalesQuote::where('sales_opportunity_id', $opptID)
                ->update([
                    'quoteToEmail' => request( 'quoteToEmail',null),
                    'quoteToPhoneNo' => request('quoteToPhoneNo', null),
                    'quote_to_name_id' => request('quote_to_name_id', null),
                    'quoteToName' => request('quoteToName', null),
                    'account_id' => $newAccountID,
                    'old_account_id' => $salesOpportunity->account_id,

                    'billingStreet' => request('billingStreet', null),
                    'billingZipCode' => request('billingZipCode', null),
                    'billingCity' => request('billingCity', null),
                    'billingState' => request('billingState', null),
                    'billing_country_id' => request('billing_country_id', null),

                    'billToName' => request('billToName', null),
                    'billEmail' => request('billEmail', null),
                    'billPhoneNo' => request('billPhoneNo', null),
                    'bill_to_name_id' => request('bill_to_name_id', null),

                    'shippingStreet' => request('shippingStreet', null),
                    'shippingZipCode' => request('shippingZipCode', null),
                    'shippingCity' => request('shippingCity', null),
                    'shippingState' => request('shippingState', null),
                    'shipping_country_id' => request('shipping_country_id', null),

                    'shipToName' => request('shipToName', null),
                    'shipEmail' => request('shipEmail', null),
                    'shipPhoneNo' => request('shipPhoneNo', null),
                    'ship_to_name_id' => request('ship_to_name_id', null),

                ]);

            return $this->respondSuccessful('Opportunity and Quotes successfuly transfered', []);

        });

    }
}
