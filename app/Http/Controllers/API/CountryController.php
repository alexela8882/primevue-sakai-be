<?php

namespace App\Http\Controllers\API;

use App\Models\Core\Country;

class CountryController extends BaseController
{
    public function all()
    {
        $countries = Country::all();

        return response()->json($countries, 200);
    }
}
