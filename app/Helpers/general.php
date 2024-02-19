<?php

use App\Models\User;
use App\Models\Gallery;
use App\Services\PicklistService;

if (!function_exists('getCollectionRawFields')) {
  function getCollectionRawFields($collection)
  {
    $data = DB::collection($collection)->raw(function ($col) {
      $cursor = $col->find();
      $array = iterator_to_array($cursor);
      $fields = array();
      foreach ($array as $k => $v) {
        foreach ($v as $a => $b) {
          $fields[] = $a;
        }
      }
      return array_values(array_unique($fields));
    });
    return $data;
  }
}

if (!function_exists('generateSelectableCollectionFields')) {
  // param1: fields
  // param2: fields to be displayed on default
  // param3: fields that will be excluded
  function generateSelectableCollectionFields($fields, $defaultKeys, $excludedKeys)
  {
    $data = [];
    $newFields = array_diff($fields, $excludedKeys);
    foreach ($newFields as $field) {
      $item = explode("_", $field);
      $related = false;

      $label = null;
      if (count($item) == 1) {
        $label = $label = $item[0];
      } else if (count($item) == 2) {
        if ($item[1] == "id") {
          $field = $item[0];
          $label = $item[0];
          $related = true;
        } else $label = $item[0] . " " . $item[1];
      }
      if ($label != null || $label != "") {
        array_push($data, [
          'field' => $field,
          'label' => ucwords($label),
          'default' => in_array($field, $defaultKeys) ? true : false,
          'related' => $related
        ]);
      }
    }
    return $data;
  }

  if (!function_exists('valid_id')) {
    function valid_id($str)
    {
      if (!is_string($str))
        return false;

      return preg_match('/^[0-9a-fA-F]{24}$/', $str);
    }
  }

  if (!function_exists('snake_case')) {
    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @param  string  $delimiter
     * @return string
     */
    function snake_case($value, $delimiter = '_')
    {
      return Str::snake($value, $delimiter);
    }
  }

  if (!function_exists('starts_with')) {
    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    function starts_with($haystack, $needles)
    {
      return Str::startsWith($haystack, $needles);
    }
  }

  if (!function_exists('ends_with')) {
    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    function ends_with($haystack, $needles)
    {
      return Str::endsWith($haystack, $needles);
    }
  }
  if (!function_exists('camel_case')) {
    /**
     * Convert a value to camel case.
     *
     * @param  string  $value
     * @return string
     */
    function camel_case($value)
    {
      return Str::camel($value);
    }
  }

  if (! function_exists('picklist_item')) {
    function picklist_item($listName, $itemId)
    {
        return (new PicklistService)->getItemById($listName, $itemId);
    }
}

if (! function_exists('picklist_value')) {
    function picklist_value($listName, $itemId)
    {
        return (new PicklistService)->getItemValue($listName, $itemId);
    }
}

if (! function_exists('picklist_id')) {
    function picklist_id($listName, $value)
    {
        return (new PicklistService)->getIDs($listName, $value);
    }
}

if (! function_exists('picklist_items')) {
    function picklist_items($listName, $idsOnly = false, $withValues = false, $listMustExist = false)
    {
        return (new PicklistService)->getListItems($listName, $idsOnly, $withValues, $listMustExist);
    }
}
}
