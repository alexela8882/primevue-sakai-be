<?php

namespace App\Services;

use App\Models\Customer\SalesOpportunity;

class SalesModuleService
{
    public function hasMissingQuote($id)
    {
        $opp = SalesOpportunity::find($id);

        if (! $opp) {
            return returnErrorMessage('Sales Opportunity with id '.$id.' not found', 422);
        }

        $opportunityItems = $opp->items()
            ->where('status_id', picklist_id('oppt_item_status', 'Active'))
            ->get();

        if (! $opportunityItems) {
            return true;
        }

        return $opportunityItems->where('sales_quote_id', null)->count() ? true : false;

    }

    public function getActiveOppItems($id, $withQuote = false, $retCollection = true)
    {

        $opp = SalesOpportunity::find($id);

        if (! $opp) {
            return returnErrorMessage('Sales Opportunity with id must be defined', 422);
        }

        $activeOppItem = picklist_id('oppt_item_status', 'Active');

        $items = $opp->items()->where('status_id', $activeOppItem);

        if ($withQuote == 'without') {
            $items = $items->where('sales_quote_id', null);
        } elseif ($withQuote) {
            $items = $items->where('sales_quote_id', '!=', null);
        }

        return $retCollection ? $items->get() ?? null : $items;

    }

    public function getStageHistoryData($id, $create = true, $oldData = null)
    {
        $data = [];

        if (! $oldData) {
            $oldData = SalesOpportunity::find($id);
        }

        if (! $create && request('stage_id') == $oldData->stage_id) {
            return null;
        }

        $data = [
            'stage_id' => request('stage_id') ?? $oldData->stage_id,
            'probability' => request('probability') ?? $oldData->probability,
            'sales_opportunity_id' => $id,
            'salesAmount' => $this->getAmount($id),
            'from_date' => $oldData->lastStageUpdateDate,
            'from_stage_id' => $create ? null : $oldData->stage_id,
            'remarks' => request('remarks', $oldData->remarks ?? null),
        ];
        $stats = picklist_id('opportunity_status', ['Closed Lost', 'Tender Lost', 'Cancelled']);

        if (in_array(request('stage_id'), $stats)) {
            $data['reason_lost_cancelled_id'] = request('reason_lost_cancelled_id', null) ? request('reason_lost_cancelled_id') : $oldData->reason_lost_cancelled_id ?? null;
        }

        return $data;
    }

    public function firstData($id)
    {
        $data = [];
        $save = true;

        $oldData = SalesOpportunity::find($id);
        $data['stage_id'] = $oldData->stage_id;
        $data['probability'] = $oldData->probability;
        $data['sales_opportunity_id'] = $id;
        $data['salesAmount'] = $this->getAmount($id);
        $data['remarks'] = $oldData->remarks ?? null;
        $data['from_stage_id'] = null;
        $date = new Carbon($oldData->created_at);
        $data['from_date'] = $date->format('Y-m-d');

        $stats[] = picklist_id('opportunity_status', 'Closed Lost');
        $stats[] = picklist_id('opportunity_status', 'Cancelled');

        if (in_array($data['stage_id'], $stats)) {
            $data['reason_lost_cancelled_id'] = $oldData->reason_lost_cancelled_id ?? null;
        }

        return $data;

        return null;
    }

    public function getAmount($id)
    {
        $opptItems = $this->getActiveOppItems($id);
        $amount = 0;
        foreach ($opptItems as $item) {
            $amount = $amount + ($item->quantity * ($item->salesPrice - ($item->salesPrice * ($item->discount ?? 0 * 100))));
        }

        return $amount;
    }

    public function checkOpportunityQuoteStat($id)
    {

        $qoutes = SalesQuote::where('sales_opportunity_id', $id)->get();

        foreach ($qoutes as $quote) {

            $this->checkQuoteStat($quote);
        }

        $salesopportunity = SalesOpportunity::find($id);

        //$this->compute($salesopportunity, "SalesOpportunity");

    }

    public function checkQuoteStat($quote, $updateOpportunity = false)
    {

        $activeOppItem = picklist_id('oppt_item_status', 'Active');
        $voidQuote = picklist_id('quote_status', 'Void');
        $activeQuote = picklist_id('quote_status', 'Active');

        $quoteCount = SalesOpptItem::where('sales_quote_id', $quote->_id)->where('status_id', $activeOppItem)->count();

        if (! $quoteCount && $quote->status_id != $voidQuote) {

            $quote->update(['status_id' => $voidQuote]);

        } elseif ($quoteCount->count() && $quote->status_id == $voidQuote) {

            $quote->update(['status_id' => $activeQuote]);
        }

        //$this->compute($qt, "SalesQuote");

        if ($updateOpportunity) {
            $salesopportunity = SalesOpportunity::find($quote->sales_opportunity_id);

            //$this->compute($salesopportunity, "SalesOpportunity");
        }

    }

    public function compute($model, $entity)
    {
        // (new Compute)->handle($model, $entity);
    }

    public function getRusAndFormula($fields, $query = null)
    {

        //for cleanup
        //change to query and not loop

        $formulaFields = [];
        $rusField = [];

        foreach ($fields as $field) {
            if ($field->fieldType->name == 'formula') {
                $formulaFields[] = $field;

                continue;
            }
            if ($field->fieldType->name == 'rollUpSummary') {
                if ($query) {
                    if ($field->rusEntity == $query) {
                        $rusField[] = $field;
                    }
                } else {
                    $rusField[] = $field;
                }

                continue;
            }
        }

        return [$formulaFields, $rusField];

    }

    public function transform($qt)
    {

        $page = [];
        $header = [];
        $body = [];
        $footer = [];

        $pageCount = $qt->pageCount;

        for ($i = 1; $i <= $pageCount; $i++) {

            $headerpanels = $qt->panels->where('pageNo', $i)->where('panelType', 'header');

            foreach ($headerpanels as $panel) {
                $header[] = ['sections' => $panel->sections];
            }

            $footerpanels = $qt->panels->where('pageNo', $i)->where('panelType', 'footer');

            foreach ($footerpanels as $panel) {
                $footer[] = ['sections' => $panel->sections];
            }

            $panels = $qt->panels->where('pageNo', $i)->whereIn('panelType', ['body', 'pagebreak', 'sectionbreak']);

            foreach ($panels as $panel) {
                if ($panel->panelType == 'body') {
                    $body[] = ['label' => $panel->label, 'isVisible' => $panel->isVisible, 'sections' => $panel->sections];
                }

                if ($panel->panelType == 'pagebreak') {
                    $body[] = ['sections' => ['elemType' => 'pagebreak']];
                }

                if ($panel->panelType == 'sectionbreak') {
                    unset($panel['panelType']);
                    unset($panel['pageNo']);
                    $body[] = ['sections' => ['elemType' => 'sectionbreak'], $panel];
                }
            }
            $page[] = ['pageNo' => $i, 'headerPanel' => $header, 'bodyPanel' => $body, 'footerPanel' => $footer];
        }

        return ['pages' => $page, 'config' => $qt->config];

    }
}
