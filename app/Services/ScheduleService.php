<?php

namespace App\Services;

use App\Models\Service\ServiceJob;

class ScheduleService
{
    public function model()
    {
        return 'App\Models\Service\ServiceJob';  // Model Class Here
    }

    public function generateJobCode()
    {
        do {
            $code = strtoupper(substr(md5(rand()), 0, 8));
            $sched = ServiceJob::where('jobCode', $code)->first();
        } while ($sched);

        return $code;
    }

    //function created fr patch fix. Please optimize this function, Cha. -Cha

    public function checkOnCall($serviceJobs, $accountId, $branchId, $update = false)
    {
        $sjs = collect([]);
        $jobStatusId = picklist_id('jobStatus', 'Active');
        $holdOldUnitID = [];

        $branchId = $branchId == 'Esco Global' ? 'esco-global' : $branchId;
        $jobType = $branchId == 'esco-global' ? picklist_id('sj_job_type', 'Global') : picklist_id('sj_job_type', 'Local');

        foreach ($serviceJobs as $serviceJob) {
            $id = $serviceJob['_id'] ?? null;

            // check if sj has contact
            if (! array_key_exists('contact', $serviceJob)) {
                $contactId = null;
            } else {
                $contactId = $serviceJob['contact']['_id'] ?? null;
            }

            //*** Validate Service Job first, all must have services.

            if (! $update) {

                // get or create Service Job
                $sj = ServiceJob::firstOrCreate([
                    '_id' => $id,
                ], [
                    'account_id' => $accountId,
                    'unit_id' => $serviceJob['unitId'] ?? null,
                    'job_status_id' => $jobStatusId,
                    'contact_id' => $contactId,
                    'address_book_id' => $serviceJob['address_book_id'] ?? null,
                    'PONo' => $serviceJob['PONo'] ?? null,
                    'svso_no' => $serviceJob['svso_no'] ?? null,
                    'description' => $serviceJob['description'] ?? null,
                    'product_category_id' => $serviceJob['product_category_id'] ?? null,
                    'branch' => $branchId,
                    'job_type_id' => $jobType,
                    'currentStatus' => 'Pending',
                    'flag_id' => $serviceJob['flag_id'] ?? null,
                    'firstScheduledDate' => null,
                    'dateCompleted' => null,
                    'lastSchedule' => null,
                    'SJType' => 'onCall',
                ]);

                if ($sj->wasRecentlyCreated) {
                    $sj->update(['oid' => $sj->_id]);
                }
            } else {
                $data = [
                    'unit_id' => $serviceJob['unitId'] ?? null,
                    'PONo' => $serviceJob['PONo'] ?? null,
                    'svso_no' => $serviceJob['svso_no'] ?? null,
                    'description' => $serviceJob['description'] ?? null,
                    'flag_id' => $serviceJob['flag_id'] ?? null,
                    'product_category_id' => $serviceJob['product_category_id'] ?? null,
                    'branch' => $branchId,
                    'job_type_id' => $jobType,
                    'contact_id' => $contactId,
                    'address_book_id' => $serviceJob['address_book_id'] ?? null,
                ];
                $sj = ServiceJob::find($id);
                if ($sj) {

                    if (array_key_exists('unitId', $serviceJob) && ($sj->unit_id ?? null) != $serviceJob['unitId']) {
                        $holdOldUnitID[] = $sj->unit_id ?? null;
                    }

                    $sj->update($data);
                } else {
                    throw new \Exception('Invalid Service Job ID.');
                }
            }
            //*** Validate Services first. Fetch product_category services and compare
            $sList = [];
            foreach ($serviceJob['service_ids'] as $service) {
                $sList[] = ['service_id' => $service['_id'], 'particular_ids' => $service['particular_ids'], 'part_ids' => $service['part_ids'] ?? []];
            }
            $this->syncJobServices($sj, $sList, $branchId, null, true);
            $sjs->push($sj);
        }

        return $update ? [$sjs, $holdOldUnitID] : $sjs;

    }

    public static function getServices(ServiceJob $serviceJob)
    {
        $services = [];
        if ($serviceJob->sales_oppt_item_ids) {
            $source = $serviceJob->belongsToMany(SalesOpptItem::class, 'sales_oppt_item_ids')->pluck('inclusive_service_ids')->toArray();
            $source = array_unique(array_flatten($source));
            $services = Service::whereIn('_id', $source)->get(['_id', 'particular_ids', 'withProduct'])->toArray();
        }

        return $services;
    }

    public static function getInputServices(ServiceJob $serviceJob, $servicelist)
    {
        $services = [];

        $servicelist = $servicelist->pluck('service_ids')->toArray();
        $servicelist = array_unique(array_flatten($servicelist));

        if ($serviceJob->sales_oppt_item_ids) {
            $source = $serviceJob->belongsToMany(SalesOpptItem::class, 'sales_oppt_item_ids')->pluck('inclusive_service_ids')->toArray();
            $source = array_unique(array_flatten($source));

            if ($servicelist) {
                $source = array_intersect($source, $servicelist);
            }
            $services = Service::whereIn('_id', $source)->get(['_id', 'particular_ids', 'withProduct'])->toArray();
        }

        return $services;
    }

    public static function syncJobServices(ServiceJob $serviceJob, $services = null, $branchId = null, $parts = null, $fromOncall = false)
    {

        if ($serviceJob->SJType == 'service' && ! $fromOncall) {
            $services = static::getInputServices($serviceJob, $services);
        } elseif ($serviceJob->SJType != 'onCall' && ! $fromOncall) {
            $services = static::getServices($serviceJob);
        }

        if ($services) {
            $services = collect($services);
            $serviceJob->jobServices()->whereNotIn('service_id', $services->pluck('service_id')->toArray())->delete();
            //add deletion

            foreach ($services as $service) {
                $sID = isset($service['service_id']) ? $service['service_id'] : $service['_id'];
                $ss = $serviceJob->jobServices()->firstOrCreate([
                    'service_id' => $sID,
                ], [
                    'service_id' => $sID,
                ]);

                if ($branchId) {
                    $ss->update(['branch_id' => $branchId]);
                }

                if ($ss->wasRecentlyCreated) {
                    $ss->update(['oid' => $ss->_id]);
                }

                $particulars = $service['particular_ids'] ?? null;

                if (is_array($particulars) && count($particulars) || is_object($particulars) && $particulars->isNotEmpty()) {
                    $ss->particulars()->sync(is_array($particulars) ? $particulars : $particulars->toArray());
                }

                $withProduct = (array_key_exists('withProduct', $service)) ? $service['withProduct'] : Service::find($sID)->withProduct ?? false;

                if ($withProduct) {

                    if (! $parts) {
                        $parts = $service['part_ids'] ?? null;
                    }

                    $ss->serviceParts()->delete();
                    $ss->parts()->detach();

                    if (! $parts) {
                        continue;
                    }

                    if (is_object($parts) && $parts->isNotEmpty()) {
                        $parts = $parts->toArray();
                    }

                    $sparts = [];

                    if (is_array($parts) && array_depth($parts) == 1) {
                        $p = [];
                        foreach ($parts as $part) {
                            $p[] = [
                                'product_id' => $part,
                                'itemCode' => 'N/A',
                                'itemDesc' => 'N/A',
                                'itemRemarks' => 'N/A',
                                'itemPrice' => 0,
                                'itemQty' => 1,
                                'total' => 0,
                                'service_job_id' => $serviceJob->_id,
                            ];
                        }
                        $sparts = $p;
                    } elseif (is_array($parts)) {
                        $sparts = $parts;
                        foreach ($sparts as $key => $part) {
                            $sparts[$key]['service_job_id'] = $serviceJob->_id;
                        }
                        $parts = array_column($parts, 'product_id');
                    }

                    if (is_array($parts) && count($parts)) {
                        $ss->serviceParts()->createMany($sparts);
                        $ss->parts()->attach(array_values($parts));

                    }
                }

            }

            $sList = $serviceJob->jobServices()->pluck('service_id')->toArray();
            $serviceJob->update(['service_ids' => $sList]);

        }
    }
}
