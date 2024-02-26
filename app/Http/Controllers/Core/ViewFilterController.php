<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\ViewFilterResource;
use App\Models\Core\ViewFilter;

class ViewFilterController extends Controller
{
    public function index()
    {

        return ViewFilterResource::collection(ViewFilter::all());

    }
}
