<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      DB::table('countries')->insert([
        'name' => "Afghanistan",
        'alpha2code' => "AF",
        'alpha3code' => "AFG",
        'numeric' => "004",
      ]);

      DB::table('countries')->insert([
        'name' => "Albania",
        'alpha2code' => "AF",
        'alpha3code' => "ALB",
        'numeric' => "008",
      ]);

      DB::table('countries')->insert([
        'name' => "Philippines (the)",
        'alpha2code' => "PH",
        'alpha3code' => "PHL",
        'numeric' => "608",
      ]);

      DB::table('countries')->insert([
        'name' => "Singapore",
        'alpha2code' => "SG",
        'alpha3code' => "SGP",
        'numeric' => "702",
      ]);

      DB::table('countries')->insert([
        'name' => "Indonesia",
        'alpha2code' => "IN",
        'alpha3code' => "IND",
        'numeric' => "360",
      ]);

      DB::table('countries')->insert([
        'name' => "Lithuania",
        'alpha2code' => "LT",
        'alpha3code' => "LTU",
        'numeric' => "440",
      ]);
    }
}
