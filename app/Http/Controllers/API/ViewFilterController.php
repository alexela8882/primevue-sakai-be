<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ViewFilterResource;
use App\Models\Core\ViewFilter;

class ViewFilterController extends Controller
{
    public function all()
    {

        return ViewFilterResource::collection(ViewFilter::all());

    }
}
