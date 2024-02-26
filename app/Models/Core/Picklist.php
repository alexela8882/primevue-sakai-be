<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Picklist extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'picklists';

    protected $fillable = [
        'name', 'values', 'catSrcListName'
    ];

    protected $appends = ['_id'];

    protected $hidden = ['listItems'];

    public function getItemsAttribute() {
        return $this->listItems;
    }

    public function listItems() {
        return $this->embedsMany('App\Models\Core\ListItem','items');
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
	
	public function getItemById($listName, $itemId){
        $picklist = $this->getList($listName);
        if($picklist){
            if (is_array($itemId))
                  return $picklist->listItems()->whereIn('_id', $itemId);

            return $picklist->listItems()->where('_id', $itemId)->first();
        }
    }


}
