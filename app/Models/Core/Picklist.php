<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Picklist extends Base
{
    protected $connection = 'mongodb';

    public function listItems()
    {
        return $this->embedsMany(ListItem::class, 'items');
    }

	protected $collection = 'picklists';

    protected $fillable = [
        'name', 'values', 'catSrcListName'
    ];

    protected $appends = ['_id'];

    protected $hidden = ['listItems'];

    public function getItemsAttribute() {
        return $this->listItems;
    }

	public function getList($listName, $throwErrorIfNotExisting = false, $itemsWithListNameAsKey = false, $checkIfActive = true){

        if(is_array($listName))
            $picklists = $this->getModel()->whereIn('name', $listName)->get();
        else
            $picklists = $this->getModel()->where('name', $listName)->first();

        if($throwErrorIfNotExisting && (!$picklists || is_array($listName) && $picklists->isEmpty())) {
            throw new \Exception('Error. Unknown picklist(s): "' . (is_array($listName) ? implode(',', $listName) : $listName) . '"');
        }

        if($itemsWithListNameAsKey) {
            if(!is_array($listName))
                $picklists = [$picklists];

            $items = [];
            foreach($picklists as $picklist) {
                if($checkIfActive)
                    // $items[$picklist->name] = makeObject([ 'values' => $picklist->listItems()->where('active', true), 'catSrcListName' => $picklist->catSrcListName ]);
                    $items[$picklist->name] = makeObject([ 'values' => array_values($picklist->listItems()->where('active', true)->sortBy('order')->all()), 'catSrcListName' => $picklist->catSrcListName ]);
                else
                    // $items[$picklist->name] = makeObject([ 'values' => $picklist->listItems()->where('_id', '!=', null), 'catSrcListName' => $picklist->catSrcListName ]);
                    $items[$picklist->name] = makeObject([ 'values' => array_values($picklist->listItems()->where('_id', '!=', null)->sortBy('order')->all()), 'catSrcListName' => $picklist->catSrcListName ]);
            }
            return $items;
        }
        else
            return $picklists;

    }

    public function updateList($listName, $items, $active = true, $report = false) {
        $picklist = $this->model->where('name', $listName)->first();
//        dd(count($items), $picklist->listItems()->count());
        $newCnt = 0; $updateCnt = 0;
        $plItems = $picklist->items;
        foreach($items as $item) {
            if(is_array($item)) {
                $item['active'] = $active;
                $data = $item;
            }
            else {
                $data = [
                    'value' => $item,
                    'active' => $active
                ];
            }

            $listItem = $plItems->where('value', $data['value'])->first();
            if(!$listItem) {
                $picklist->listItems()->create($data);
                $newCnt++;
            }
            else {
                $listItem->update($data);
                $updateCnt++;
            }
        }
        if($report) {
            echo "New items: " . $newCnt . " ; Updated items: " . $updateCnt;
        }
    }

    public function getListItems($listName, $idsOnly = false, $withValues = false, $listMustExist = false){

        $picklist = $this->getList($listName, $listMustExist);
        if($picklist) {
            if($idsOnly) {
                if($withValues)
                    return $picklist->listItems()->pluck('_id', 'value');

                return $picklist->listItems()->pluck('_id');
            }
            elseif($withValues)
                return $picklist->listItems()->pluck('value');

            return $picklist->listItems;
        }

    }

    public function getItemById($listName, $itemId){
        $picklist = $this->getList($listName);
        if($picklist){
            if (is_array($itemId))
                  return $picklist->listItems()->whereIn('_id', $itemId);

            return $picklist->listItems()->where('_id', $itemId)->first();
        }
    }

    public function getItemValue($listName, $itemId){
        if (is_array($itemId))
            return $this->getItemById($listName, $itemId)->pluck('value');
        
        return $this->getItemById($listName, $itemId)->value ?? null;
    }

    public function getIDs($listName, $itemName, $catKey = null, $key = 'value'){
        $picklist = $this->getList($listName);
        $list = null;
        if($picklist){
            if (is_array($itemName)){
                $list = $picklist->listItems()->whereIn($key, $itemName);
                if(!$list)
                    $list = $this->getItemById($listName, $itemName);
                if($list)
                    $list= $list->pluck('_id')->toArray();
            }

            elseif(is_string($itemName)){
                $list = $picklist->listItems()->where($key, $itemName)->first();
                if(!$list)
                    $list = $this->getItemById($listName, $itemName);

                if($list)
                    $list= $list->_id;
            }
            elseif($catKey) {
                $list = $picklist->listItems->filter(function($listItem) use ($catKey) {
                    if (is_array($listItem->category_key))
                        return in_array($catKey, $listItem->category_key);
                    else 
                        return $catKey === $listItem->category_key;
                })->pluck('_id')->toArray();
            }
        }
        return $list;
    }

    public function deleteLists($name = []) {
        return $this->model->whereIn('name', $name)->delete();
    }

    public function createList($listName, $list = []){

        $list = $this->buildList($listName, $list);

        return $this->saveBuilt($list->name, $list->options);
    }

    public function buildList($listName, $list = [], $active = true, $catSrcListName = null)
    {
        $items = collect([]);

        foreach ($list as $item) {
            if (is_array($item)) {
                $item['active'] = $active;
                $data = $item;
            } else
                $data = ['value' => $item, 'active' => $active];

            $items->push($data);
        }

        $items = $items->sortBy('value')->values()->map(function ($item, $key) {
            $item['order'] = $key;

            return new ListItem($item);
        })->all();


        $data = new StdClass();
        $data->name = $listName;
        $data->options = $items;
        
        if ($catSrcListName)
            $data->catSrcListName = $catSrcListName;

        return $data;
    }

    public function saveBuilt($listName, $list, $catSrcListName = null) {
        $pickList = [
            'name' => $listName
        ];
        if($catSrcListName)
            $pickList['catSrcListName'] = $catSrcListName;

        $pickList = $this->model->firstOrCreate($pickList, $pickList);
        $pickList->listItems()->saveMany($list);

        return $pickList;
    }


    public function getPicklistsFromFields($fields) {
        // Get picklists
        $picklistNames = [];
        foreach($fields as $field) {
            if($field->fieldType->name == 'picklist') {
                $picklistNames[] = $field->listName;
            }
        }

        if(!count($picklistNames))
            return [];

        $picklistArrays = $this->getList($picklistNames, true, true, false);

        $picklists = [];


        foreach($picklistArrays as $key => $picklistItems) {
            $picklists[ $key ] = [];
            foreach($picklistItems->values as $picklistItem) {
                $picklists[$key][ $picklistItem['_id'] ] = (string) $picklistItem['value'];
            }
        }

        return $picklists;
    }


    public function getPicklistsForReport($fields) {
        // Get picklists
        $picklistNames = [];
        foreach($fields as $field) {
            if($field->fieldType->name == 'picklist') {
                $picklistNames[] = $field->listName;
            }
        }

        if(!count($picklistNames))
            return [];

        $picklistArrays = $this->getList($picklistNames, true, true);

        $picklists = [];


        foreach($picklistArrays as $key => $picklistItems) {
            $picklists[ $key ] = [];
            foreach($picklistItems->values as $picklistItem) {
                $picklists[$key][ $picklistItem['_id'] ] = (string) $picklistItem['value'];
            }
        }

        return $picklists;
    }


    public function getOperators($val = null)
    {
        // Modified September 29, 2021 Hobert Mejia
        // Commented the following because it only get the operator once

        // if (!$this->operators) {
            if (!$val)
                $this->operators = $this->getListItems('filter_operators');
            else {
                $picklist = $this->getList('filter_operators', true);
                $this->operators = $picklist->listItems('filter_operators')->filter(function ($item) use ($val) {
                    return $item->_id == $val || $item->value == $val;
                })->first();
            }
        // }

        return $this->operators;
    }

    public function fetchCountries() {

        $client = new GuzzleHttp\Client();

        $url = "https://restcountries.eu/rest/v2/all";

        $response = $client->request('GET', $url );

        $results = json_decode($response->getBody());


    }
}
