<?php

namespace App\Services;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;

class ReportService
{
    protected static $cy = ['Current CY', 'Previous CY', 'Previous 2 CY', '2 CY Ago', 'Next CY', 'Current and Previous CY', 'Current and Previous 2 CY', 'Current and Next CY'];

    protected static $cq = ['Current CQ', 'Current and Next CQ', 'Current and Previous CQ', 'Next CQ', 'Previous CQ', 'Current and Next 3 CQ'];

    protected static $m = ['Last Month', 'This Month', 'Next Month', 'Current and Previous Month', 'Current and Next Month'];

    protected static $w = ['Last Week', 'This Week', 'Next Week'];

    protected static $d = ['Yesterday', 'Today', 'Tomorrow', 'Last 7 Days', 'Last 30 Days', 'Last 60 Days', 'Last 120 Days', 'Next 7 Days', 'Next 30 Days', 'Next 60 Days', 'Next 90 Days', 'Next 120 Days'];

    public function getFromToRange($t)
    {

        $from = null;
        $to = null;

        $type = PickList::getItemById('date_range', $t)->value;

        if (in_array($type, static::$cy)) {
            switch ($type) {
                case 'Current CY':
                    $from = (new Carbon('now'))->firstOfYear();
                    $to = (new Carbon('now'))->lastOfYear();
                    break;
                case 'Previous CY':
                    $from = (new Carbon('now'))->subYear()->firstOfYear();
                    $to = (new Carbon('now'))->subYear()->lastOfYear();
                    break;
                case 'Previous 2 CY':
                    $from = (new Carbon('now'))->subYear(2)->firstOfYear();
                    $to = (new Carbon('now'))->subYear()->lastOfYear();
                    break;
                case '2 CY Ago':
                    $from = (new Carbon('now'))->subYear(2)->firstOfYear();
                    $to = (new Carbon('now'))->subYear(2)->lastOfYear();
                    break;
                case 'Next CY':
                    $from = (new Carbon('now'))->addYear()->firstOfYear();
                    $to = (new Carbon('now'))->addYear()->lastOfYear();
                    break;
                case 'Current and Previous CY':
                    $from = (new Carbon('now'))->subYear()->firstOfYear();
                    $to = (new Carbon('now'))->lastOfYear();
                    break;
                case 'Current and Previous 2 CY':
                    $from = (new Carbon('now'))->subYear(2)->firstOfYear();
                    $to = (new Carbon('now'))->lastOfYear();
                    break;
                default:
                    $from = (new Carbon('now'))->firstOfYear();
                    $to = (new Carbon('now'))->addYear()->lastOfYear();
                    break;
            }
        } elseif (in_array($type, static::$cq)) {
            switch ($type) {
                case 'Current CQ':
                    $from = (new Carbon('now'))->firstOfQuarter();
                    $to = (new Carbon('now'))->lastOfQuarter();
                    break;
                case 'Current and Next CQ':
                    $from = (new Carbon('now'))->firstOfQuarter();
                    $to = (new Carbon('now'))->addQuarter()->lastOfQuarter();
                    break;
                case 'Current and Previous CQ':
                    $from = (new Carbon('now'))->subQuarter()->firstOfQuarter();
                    $to = (new Carbon('now'))->lastOfQuarter();
                    break;
                case 'Next CQ':
                    $from = (new Carbon('now'))->addQuarter()->firstOfQuarter();
                    $to = (new Carbon('now'))->addQuarter()->lastOfQuarter();
                    break;
                case 'Previous CQ':
                    $from = (new Carbon('now'))->subQuarter()->firstOfQuarter();
                    $to = (new Carbon('now'))->subQuarter()->lastOfQuarter();
                    break;
                default:
                    $from = (new Carbon('now'))->firstOfYear();
                    $to = (new Carbon('now'))->firstOfQuarter()->addQuarter(3)->lastOfQuarter();
                    break;
            }
        } elseif (in_array($type, static::$m)) {
            switch ($type) {
                case 'Current and Previous Month':
                    $from = (new Carbon('now'))->subMonth()->firstOfMonth();
                    $to = (new Carbon('now'))->lastOfMonth();
                    break;
                case 'Current and Next Month':
                    $from = (new Carbon('now'))->firstOfMonth();
                    $to = (new Carbon('now'))->addMonth()->lastOfMonth();
                    break;
                default:

                    $from = (new Carbon($type))->firstOfMonth();
                    $to = (new Carbon($type))->lastOfMonth();

                    break;
            }
        } elseif (in_array($type, static::$w)) {
            $x = new Carbon($type);
            $from = $x->startOfWeek();
            $to = $x->endOfWeek();
        } elseif (in_array($type, static::$d)) {
            if (in_array($type, ['Yesterday', 'Today', 'Tomorrow'])) {
                $x = new Carbon($type);
                $from = $x;
                $to = $x;
            } else {
                $x = explode(' ', $type);
                if ($x[0] == 'Next') {
                    $from = (new Carbon('now'));
                    $to = (new Carbon('now'))->addDays((int) $x[1]);
                } else {
                    $from = (new Carbon('now'))->subDays((int) $x[1]);
                    $to = (new Carbon('now'));
                }
            }
        } else {
            $from = new Carbon($type);
            $to = $from;
        }

        return [$from, $to];

    }

    public function isMultiSelect($rules)
    {
        return count(array_intersect(array_column($rules, 'name'), ['ms_dropdown', 'ms_list_view', 'checkbox_inline', 'checkbox', 'tab_multi_select', 'ms_pop_up'])) > 0;
    }

    public function getDateQuery($dateFieldName, $rangeFrom, $rangeTo)
    {

        $systemDate = ['created_at', 'updated_at', 'deleted_at'];

        if (! in_array($dateFieldName, $systemDate)) {
            if ($rangeFrom != $rangeTo) {
                return ['$match' => [$dateFieldName => ['$gte' => $rangeFrom->format('Y-m-d'), '$lte' => $rangeTo->format('Y-m-d')]]];
            }

            return ['$match' => [$dateFieldName => $rangeFrom->format('Y-m-d')]];
        }

        $rangeFrom = new UTCDateTime((new \DateTime($rangeFrom->startOfDay()))->getTimestamp() * 1000);
        $rangeTo = new UTCDateTime((new \DateTime($rangeTo->endOfDay()))->getTimestamp() * 1000);

        if ($rangeFrom != $rangeTo) {
            return ['$match' => [$dateFieldName => ['$gte' => $rangeFrom, '$lte' => $rangeTo]]];
        }

        return ['$match' => [$dateFieldName => $rangeFrom]];

    }

    public function getDateRange($dateField)
    {
        $rangetype['All Time'] = picklist_id('date_range', 'All Time');
        $rangetype['Custom'] = picklist_id('date_range', 'Custom');

        if (request('rangeType', null) == $rangetype['All Time']) {
            $d1 = $dateField->entity->getModel()->orderBy($dateField->name)->first()->{$dateField->name} ?? null;
            $d2 = $dateField->entity->getModel()->orderBy($dateField->name, 'DESC')->first()->{$dateField->name} ?? null;

            if (is_array($d1) && array_key_exists('date', $d1)) {
                $d1 = $d1['date'];
            }
            if (is_array($d2) && array_key_exists('date', $d2)) {
                $d2 = $d2['date'];
            }

            return [new Carbon($d1 ?? 'Today'), new Carbon($d2 ?? 'Today')];
        } elseif (request('rangeType', null) != $rangetype['Custom']) {
            return $this->getFromToRange(request('rangeType'));
        } elseif (request('rangeFrom') && request('rangeTo')) {
            return [new Carbon(request('rangeFrom')), new Carbon(request('rangeTo'))];
        } elseif (request('rangeForm')) {
            return [new Carbon(request('rangeFrom')), new Carbon(request('rangeFrom'))];
        }

        throw new \Exception('Error. Undefined date range');
    }
}
