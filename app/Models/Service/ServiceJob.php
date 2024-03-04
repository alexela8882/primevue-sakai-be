<?php

namespace App\Models\Service;

use App\Models\Customer\Account;
use App\Models\Customer\SalesOpptItem;
use App\Models\Model\Base;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\Unit;
use App\Models\Service\ScheduleServiceJob as ScheduleServiceJob;
use App\Models\Service\Service as Service;

class ServiceJob extends Base
{
    public static $cancelledSchedStatus;

    public static $closedSchedStatus;

    public static $stat;

    public static $inactiveSJ;

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function scheduleServiceJob()
    {
        return $this->hasMany(ScheduleServiceJob::class);
    }

    public function salesOpptItem()
    {
        return $this->belongsToMany(SalesOpptItem::class, null, 'service_job_ids', 'sales_oppt_item_ids');
    }

    public function parts()
    {
        return $this->belongsToMany(Product::class, null, 'service_job_ids', 'part_ids');
    }

    public function onCallServiceList()
    {
        return $this->hasMany(onCallServiceList::class);
    }

    public function jobServices()
    {
        return $this->belongsToMany(ServiceJobService::class);
    }

    public function servicelist()
    {
        return $this->belongsToMany(Service::class, null, 'service_job_ids', 'service_ids');
    }

    public function getEndDate()
    {
        $sched = $this->scheduleServiceJob()->orderBy('created_at', 'DESC')->first();
        if ($sched && in_array($sched->serviceSchedule->schedule_status_id, [$this->getCancelledSchedStat(), $this->getClosedSchedStat()])) {
            return $sched->serviceSchedule->updated_at;
        }

        return null;
    }
}
