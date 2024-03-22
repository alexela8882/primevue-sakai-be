<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Core\Entity;
use App\Models\Core\Relation;
use App\Models\Core\ViewFilter;
use App\Models\User;
use App\Models\UserConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // $user = User::create([
        //     'name' => 'super admin',
        //     'email' => 'super@admin.com',
        //     'password' => Hash::make('superadmin'),
        // ]);

        // UserConfig::create([
        //     'user_id' => $user->_id,
        //     'app_theme' => 'esco',
        //     'app_theme_dark' => 'light',
        //     'app_theme_ripple' => true,
        //     'app_theme_input_style' => 'outlined',
        //     'app_theme_menu_type' => 'static',
        //     'app_theme_scale' => '14',
        // ]);

        $this->changeRelationUserModelClass();
        $this->renameConnectionIdToConnectionIdsInEntityCollections();
        $this->massUpdateAllFiltersOfAViewFiltersAndMakeThemToArrayIfItIsNull();
        $this->changeUserModuleModelClassOnEntityCollection();

        if (App::environment('local')) {
            $this->testingArea();
        }
    }

    public function changeRelationUserModelClass($isForV2 = true)
    {
        // Changed the saved class in each Relation document
        // from V1's App\User to V2's App\Models\User

        if ($isForV2) {
            Relation::query()
                ->where('class', 'App\User')
                ->each(fn (Relation $relation) => $relation->updateQuietly(['class' => 'App\Models\User']));

            dump("Changed all relations with class of 'App\User' to 'App\Models\User'.");
        } else {
            Relation::query()
                ->where('class', 'App\Models\User')
                ->each(fn (Relation $relation) => $relation->updateQuietly(['class' => 'App\User']));

            dump("Changed all relations with class of 'App\Models\User' to 'App\User'.");
        }
    }

    public function renameConnectionIdToConnectionIdsInEntityCollections()
    {
        // Renaming the saved connection_id object field name of Entity to connection_ids

        Entity::each(function (Entity $entity) {
            $entity->updateQuietly([
                '$rename' => ['connection_id' => 'connection_ids'],
            ]);
        });

        dump("Renamed all entity collections' connection_id field to connection_ids.");
    }

    public function massUpdateAllFiltersOfAViewFiltersAndMakeThemToArrayIfItIsNull()
    {
        // Change the structure of filters
        // If value of filters is null, then we just put empty array
        // so that our ViewFilterResource's customItemCollection function will not have an error
        // [
        //      'field_id' => <field_id>,
        //      'operator_id' => <operator_id>
        //      'values' => <values>
        // ]

        ViewFilter::query()
            ->each(function (ViewFilter $viewFilter) {
                if (is_array($viewFilter->filters)) {
                    $filters = Arr::map($viewFilter->filters, function ($array) {
                        return [
                            'field_id' => $array['field_id'] ?? $array[0],
                            'operator_id' => $array['field_id'] ?? $array[1],
                            'values' => $array['field_id'] ?? $array[2],
                        ];
                    });
                } elseif (is_null($viewFilter->filters)) {
                    $filters = [];
                }

                $viewFilter->update(['filters' => $filters]);
            });

        dump('Mass updated all filters of each view filters based on discussed object structure.');
    }

    public function testingArea()
    {
        //
    }

    public function changeUserModuleModelClassOnEntityCollection()
    {
        Entity::where('model_class', 'App\User')->update([
            'model_class' => 'App\Models\User',
        ]);

        dump("Changed all entity model class of user module from App\User to App\Models\User");
    }
}
