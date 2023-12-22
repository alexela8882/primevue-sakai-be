<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;

use App\Models\Country;

class CountryController extends BaseController
{
  public function all () {
    $countries = Country::all();

    return response()->json($countries, 200);
  }
}
