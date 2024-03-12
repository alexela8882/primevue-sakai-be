<?php

namespace Database\Seeders;

use App\Models\Core\ViewFilter;
use App\Models\Module\Module;
use Illuminate\Database\Seeder;

class ViewFilterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->massUpdateModuleName();
    }

    public function massUpdateModuleName()
    {

        $viewFilters = ViewFilter::all();

        foreach ($viewFilters as $viewFilter) {

            $module = Module::where('name', $viewFilter->moduleName)->first();
            $viewFilter->update(['module_id' => $module->id]);

        }

        dump('Successfully updated.');

    }
}
