<?php

namespace App\Http\Resources\Static;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Static\ActivityLog;
use Illuminate\Support\Carbon;

class ActivityLogResource extends JsonResource
{
    public static function customItemCollection ($resource) {
      return $resource;
    }

    public static function customCollection ($resources) {
      return $resources;
    }

    public static function groupedDateCollection($resources = null, $item = null) {
      $resources = $resources ?? ActivityLog::all();

      $resources = $resources->groupBy(function ($item) {
        return $item['created_at']->format('Y-m-d');
      })->map(function ($group, $key) use ($item) {
        $carbonDate = Carbon::parse($group->first()['created_at']);
        $datePhrase = $carbonDate->diffInMonths(Carbon::now()) == 0 ? 'This month' : ($carbonDate->diffInMonths(Carbon::now()) == 1 ? '1 month ago' : $carbonDate->diffInMonths(Carbon::now()) . ' months ago');

        // Filter items based on $item->_id if $item is provided
        $filteredItems = $item ? $group->where('_id', $item->_id)->values() : $group;

        return [
          'date' => $group->first()['created_at']->format('Y-m-d'),
          'date_mdy' => $group->first()['created_at']->format('M d, Y'),
          'date_dfh' => $group->first()['created_at']->diffForHumans(),
          'date_phrase' => $datePhrase,
          'count' => $filteredItems->count(),
          'items' => $filteredItems->toArray() // Include all attributes
        ];
      })->values();
  
      return $resources;
    }

    // public static function groupedDateCollection ($resources, $item = null) {
    //   $resources = $resources->groupBy(function ($item) {
    //     return $item['created_at']->format('Y-m-d');
    //   })->map(function ($group, $key) {
    //     $carbonDate = Carbon::parse($group->first()['created_at']);
    //     $datePhrase = $carbonDate->diffInMonths(Carbon::now()) == 0 ? 'This month' : ($carbonDate->diffInMonths(Carbon::now()) == 1 ? '1 month ago' : $carbonDate->diffInMonths(Carbon::now()) . ' months ago');
    //     return [
    //       'date' => $group->first()['created_at']->format('Y-m-d'),
    //       'date_mdy' => $group->first()['created_at']->format('M d, Y'),
    //       'date_dfh' => $group->first()['created_at']->diffForHumans(),
    //       'date_phrase' => $datePhrase,
    //       'count' => $group->count(),
    //       'items' => $group->toArray() // Include all attributes
    //     ];
    //   })->values();

    //   return $resources;
    // }
}
