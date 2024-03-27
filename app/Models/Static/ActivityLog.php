<?php

namespace App\Models\Static;

use App\Models\Model\Base;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class ActivityLog extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'activity_logs';

    protected $appends = [
        'created_at_mdy',
        'created_at_dfh',
        'updated_at_mdy',
        'updated_at_dfh',
        'date_mdy',
        'date_dfh',
    ];

    /**
     * Human readable created_at formatted to 'Month Date, year'
     */
    public function createdAtMdy(): Attribute
    {
        return new Attribute(
            get: fn (mixed $value, array $attributes) => Carbon::parse(strtotime($attributes['created_at']))->format('M d, Y')
        );
    }

    /**
     * Human readable created_at
     */
    public function createdAtDfh(): Attribute
    {
        return new Attribute(
            get: function ($value, $attributes) {
                // Convert MongoDB UTCDateTime to Carbon
                $carbonDate = Carbon::instance($attributes['created_at']->toDateTime());

                // Use diffForHumans() on the Carbon object
                return $carbonDate->diffForHumans();
            }
        );
    }

    /**
     * Human readable updated_at formatted to 'Month Date, year'
     */
    public function updatedAtMdy(): Attribute
    {
        return new Attribute(
            get: fn (mixed $value, array $attributes) => Carbon::parse(strtotime($attributes['updated_at']))->format('M d, Y')
        );
    }

    /**
     * Human readable updated_at
     */
    public function updatedAtDfh(): Attribute
    {
        return new Attribute(
            get: function ($value, $attributes) {
                // Convert MongoDB UTCDateTime to Carbon
                $carbonDate = Carbon::instance($attributes['updated_at']->toDateTime());

                // Use diffForHumans() on the Carbon object
                return $carbonDate->diffForHumans();
            }
        );
    }

    /**
     * Human readable date formatted to 'Month Date, year'
     */
    public function dateMdy(): Attribute
    {
        return new Attribute(
            get: fn (mixed $value, array $attributes) => Carbon::parse(strtotime($attributes['date']))->format('M d, Y')
        );
    }

    /**
     * Human readable date
     */
    public function dateDfh(): Attribute
    {
        return new Attribute(
            get: function ($value, $attributes) {
                // Convert the raw date to a Carbon instance
                $carbonDate = Carbon::parse($attributes['date']);

                // Use diffForHumans() on the Carbon object
                return $carbonDate->diffForHumans();
            }
        );
    }
}
