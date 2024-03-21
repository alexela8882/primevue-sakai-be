<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Customer\SalesOpportunity;
use App\Models\Customer\SalesOpptItem;
use App\Models\Product\Unit;
use App\Traits\ApiResponseTrait;

class ServiceJobController extends Controller
{
    use ApiResponseTrait;

    private $user;

    public function __construct(private ModuleDataCollector $moduleDataCollector)
    {
        $this->moduleDataCollector->setUser()->setModule('servicejobs');
    }

    public function generateSJ($id)
    {

        $oppt = SalesOpportunity::find($id);

        return $this->respondUnprocessable('Error. Invalid Opportunity Id');

        $gen = false;

        $activeOppItem = picklist_id('oppt_item_status', 'Active');
        $brand = picklist_id('brand_type', 'Esco Brand');
        $items = request('items') ?? [];
        $jobs = request('jobs') ?? [];
        $activeSJ = picklist_id('jobStatus', 'Active');
        $account = request('account_id') ?? $oppt->account_id;

        $branchID = $this->user->_id == '5d889032a6ebc7d6d43cb202' ? '5d3fe1dca6ebc7bd0372c542' : $oppt->branch_id;

        foreach ($items as $item) {

            $opptItem = SalesOpptItem::find($item['_id']);

            if ($opptItem->inclusive_service_ids ?? null) {
                //create Unit

                $eUnits = Unit::where('sales_oppt_item_id', $opptItem->_id)->pluck('_id')->toArray();
                $uUnits = [];
                $cat = Product::find($opptItem->product_id)->categories()->first()->_id;

                for ($i = 0; $i < intval($item['qty']); $i++) {

                    $aUnits = array_values(array_diff($eUnits, $uUnits));

                    if ($aUnits) {
                        $sUnit = Unit::find($aUnits[0]);
                        $uUnits[] = $aUnits[0];
                    } else {
                        $sUnit = Unit::create([
                            'product_id' => $opptItem->product_id,
                            'account_id' => $account,
                            idify('brand') => $brand,
                            'category_id' => $cat,
                            'branch_id' => $oppt->branch_id,
                            'sales_oppt_item_id' => $opptItem->_id,
                        ]);

                        $sUnit->update(['oid' => $sUnit->_id]);
                    }

                    $sj = ServiceJob::firstorCreate([
                        'sales_opportunity_id' => $oppt->_id,
                        'unit_id' => $sUnit->_id,
                    ], [
                        'account_id' => $account,
                        'jobCode' => (new ScheduleService)->generateJobCode(),
                        'unit_id' => $sUnit->_id,
                        'job_status_id' => $activeSJ,
                        'SJType' => 'sales',
                        'currentStatus' => 'Pending',
                        'contact_id' => request('contact_id'),
                        'PONo' => $oppt->PONo,
                        'svso_no' => request('svso_no') ?? null,
                        'sales_opportunity_id' => $oppt->_id,
                        'branch' => $branchID,
                        'description' => $item['description'] ?? null,
                        'product_category_id' => $cat,
                    ]);

                    $sj->salesOpptItem()->attach($opptItem->_id);
                    $sj->update(['oid' => $sj->_id]);
                    $gen = true;
                    ServiceJobSourceResolver::syncJobServices($sj);
                }
            }
        }

        $ptype = picklist_id('product_types', ['Replacement Part', 'Accessory']);

        foreach ($jobs as $item) {

            $param_items = null;

            if (array_depth($item['_ids']) > 1) {
                $param_items = collect($item['_ids']);
                $oppitemids = $param_items->pluck('_id')->toArray();
            } else {
                $oppitemids = $item['_ids'];
            }

            $opptItems = SalesOpptItem::whereIn('_id', $oppitemids)->where('inclusive_service_ids', '!=', null)->get();
            $parts = null;

            foreach ($param_items as $pi) {

                $oi = $opptItems->find($pi['_id']);
                $prod = Product::find($oi->product_id);
                if (! $prod) {
                    continue;
                }

                if (in_array($prod->product_type_id, $ptype)) {
                    $parts[] = [
                        'product_id' => $prod->_id,
                        'itemCode' => 'N/A',
                        'itemDesc' => 'N/A',
                        'itemRemarks' => $pi['itemRemarks'] ?? null,
                        'itemPrice' => 0,
                        'itemQty' => $pi['itemQty'] ?? 1,
                        'total' => 0,
                    ];
                }
            }

            if ($opptItems) {
                $unit = Unit::find($item['unit_id']);
                $cat = $unit->category_id;

                $sj = ServiceJob::create([
                    'account_id' => $account,
                    'jobCode' => (new ScheduleService)->generateJobCode(),
                    'unit_id' => $unit->_id,
                    'job_status_id' => $activeSJ,
                    'SJType' => 'service',
                    'contact_id' => request('contact_id'),
                    'PONo' => $oppt->PONo,
                    'svso_no' => request('svso_no') ?? null,
                    'sales_opportunity_id' => $oppt->_id,
                    'branch' => $branchID,
                    'currentStatus' => 'Pending',
                    'description' => $item['description'] ?? null,
                    'product_category_id' => $cat,
                ]);
                $sj->salesOpptItem()->attach($opptItems->pluck('_id')->toArray());
                $sj->update(['oid' => $sj->_id]);

                $gen = true;
                ServiceJobSourceResolver::syncJobServices($sj, $param_items, null, $parts);
            }
        }

        if ($oppt) {
            $oppt->update(['generatedSJ' => true]);
        }

        if ($gen) {
            $mes = 'Service jobs were created successfully.';
        } else {
            $mes = 'No service job generated.';
        }

        return ['message' => $mes];
    }
}
