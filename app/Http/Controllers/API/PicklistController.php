<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\PicklistResource;
use App\Models\Core\Entity;
use App\Models\Core\Picklist;

class PicklistController extends Controller
{
	protected $picklist;
    protected $entity;

    public function __construct(PickList $pickList, Entity $entity) {
        $this->picklist = $pickList;
        $this->entity = $entity;
    }

	public function getLists(Request $request)	
    {
		$listName = $request->get('listName');
		return $this->picklist->getList($listName, true, true);
    }
}
