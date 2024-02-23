<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\PicklistResource;
use App\Models\Core\Picklist;

class PicklistController extends Controller
{
    public function all()
    {

        return PicklistResource::collection(Picklist::all());

    }
}
