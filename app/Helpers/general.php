<?php

use App\Models\User;
use App\Models\Gallery;

if (! function_exists('getCollectionRawFields')) {
  function getCollectionRawFields($collection) {
    $data = DB::collection($collection)->raw(function($col) {
      $cursor = $col->find();
      $array = iterator_to_array($cursor);
      $fields = array();
      foreach ($array as $k=>$v) {
        foreach ($v as $a=>$b) {
          $fields[] = $a;
        }
      }
      return array_values(array_unique($fields)); 
    });
    return $data;
  }
}

if (! function_exists('generateSelectableCollectionFields')) {
  // param1: fields
  // param2: fields to be displayed on default
  // param3: fields that will be excluded
  function generateSelectableCollectionFields($fields, $defaultKeys, $excludedKeys) {
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
        }
        else $label = $item[0] . " " . $item[1];
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
}
