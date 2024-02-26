<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\PicklistResource;
use App\Models\Core\Entity;
use App\Models\Core\Picklist;
use Illuminate\Http\Request;

class PickListController extends Controller
{
    protected $picklist;

    protected $entity;

    public function __construct(PickList $pickList, Entity $entity)
    {
        $this->picklist = $pickList;
        $this->entity = $entity;
    }

    public function getList($listName)
    {
        return $this->respondFriendly(function () use ($listName) {
            return $this->respond($this->picklist->getList($listName, true, true));
        });
    }

    public function getLists(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            $listName = $request->get('listName');

            return $this->picklist->getList($listName, true, true);
        });
    }

    public function index()
    {
        return PicklistResource::collection(Picklist::all());
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
