<?php

use App\Services\PicklistService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

if (! function_exists('getCollectionRawFields')) {
    function getCollectionRawFields($collection)
    {
        $data = DB::collection($collection)->raw(function ($col) {
            $cursor = $col->find();
            $array = iterator_to_array($cursor);
            $fields = [];
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

if (! function_exists('generateSelectableCollectionFields')) {
    // param1: fields
    // param2: fields to be displayed on default
    // param3: fields that will be excluded
    function generateSelectableCollectionFields($fields, $defaultKeys, $excludedKeys)
    {
        $data = [];
        $newFields = array_diff($fields, $excludedKeys);
        foreach ($newFields as $field) {
            $item = explode('_', $field);
            $related = false;

            $label = null;
            if (count($item) == 1) {
                $label = $label = $item[0];
            } elseif (count($item) == 2) {
                if ($item[1] == 'id') {
                    $field = $item[0];
                    $label = $item[0];
                    $related = true;
                } else {
                    $label = $item[0].' '.$item[1];
                }
            }
            if ($label != null || $label != '') {
                array_push($data, [
                    'field' => $field,
                    'label' => ucwords($label),
                    'default' => in_array($field, $defaultKeys) ? true : false,
                    'related' => $related,
                ]);
            }
        }

        return $data;
    }

    if (! function_exists('valid_id')) {
        function valid_id($str)
        {
            if (! is_string($str)) {
                return false;
            }

            return preg_match('/^[0-9a-fA-F]{24}$/', $str);
        }
    }

    if (! function_exists('snake_case')) {
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

    if (! function_exists('starts_with')) {
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

    if (! function_exists('ends_with')) {
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
    if (! function_exists('title_case')) {
        function title_case(string $str)
        {
            return Str::title($str);
        }
    }

    if (! function_exists('is_valid_date')) {
        function is_valid_date($date, $format = 'Y-m-d')
        {
            $d = DateTime::createFromFormat($format, $date);

            return $d && $d->format($format) == $date;
        }
    }

    if (! function_exists('left')) {

        function left($str, $length)
        {
            return substr($str, 0, $length);
        }
    }

    if (! function_exists('right')) {

        function right($str, $length)
        {
            return substr($str, -$length);
        }
    }

    if (! function_exists('stringify')) {

        function stringify($name)
        {
            return starts_with($name, '"') ? $name : '"'.$name.'"';
        }
    }

    if (! function_exists('camel_case')) {
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

    function str_plural($value, $count = 2)
    {
        return Str::plural($value, $count);
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

    if (! function_exists('returnErrorMessage')) {
        function returnErrorMessage($message, $code)
        {
            return [
                'message' => 'Error. '.$message,
                'status_code' => 422,
            ];
        }
    }

    if (! function_exists('picklist_items')) {
        function picklist_items($listName, $idsOnly = false, $withValues = false, $listMustExist = false)
        {
            return (new PicklistService)->getListItems($listName, $idsOnly, $withValues, $listMustExist);
        }
    }

    if (! function_exists('makeObject')) {

        function makeObject($array = [])
        {
            $object = new \StdClass();
            foreach ($array as $key => $item) {
                $object->{$key} = $item;
            }

            return $object;
        }

    }

}

if (! function_exists('array_depth')) {

    function array_depth(array $array)
    {
        $max_depth = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = array_depth($value) + 1;

                if ($depth > $max_depth) {
                    $max_depth = $depth;
                }
            }
        }

        return $max_depth;
    }
}

if (! function_exists('idify')) {

    function idify($name)
    {

        if (Str::endsWith($name, ['_id', '_ids'])) {
            return $name;
        }

        if (Str::plural($name) == $name) {
            $str = Str::snake(Str::singular($name)).'_ids';
        } else {
            $str = Str::snake($name).'_id';
        }

        return $str;
    }
}

if (! function_exists('logDrf')) {
    function logDrf($value)
    {
        Log::info($value);
    }
}
