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

        if (App::environment('local')) {
            $this->testingArea();
        }
    }

    public function testingArea()
    {

        [
            [
                'uuid' => '65f7da169014a',
                'field_id' => '5c906a14a6ebc7193110ef94',
                'operator_id' => '5bb104cf678f71061f643c2f',
                'values' => ['60bdd7c6a6ebc77ede159522', '5d36d395a6ebc7301e5601d3', '60bdd7c6a6ebc77ede159523', '60bdd7c6a6ebc77ede159524'],
            ],
            [
                'field_id' => '5c906a15a6ebc7193110eff4', 'operator_id' => '5bb104cf678f71061f643c2a', 'values' => true,
            ],

        ];
    }

    public function changeRelationUserModelClass($isForV2 = true)
    {
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
        Entity::each(function (Entity $entity) {
            $entity->updateQuietly([
                '$rename' => ['connection_id' => 'connection_ids'],
            ]);
        });

        dump("Renamed all entity collections' connection_id field to connection_ids.");
    }

    public function massUpdateAllFiltersOfAViewFiltersAndMakeThemToArrayIfItIsNull()
    {
        ViewFilter::query()
            ->each(function (ViewFilter $viewFilter) {
                $filters = Arr::map(Arr::wrap($viewFilter->filters), function ($array) {
                    return [
                        'field_id' => $array['field_id'] ?? $array[0],
                        'operator_id' => $array['field_id'] ?? $array[1],
                        'values' => $array['field_id'] ?? $array[2],
                    ];
                });

                $viewFilter->update(['filters' => $filters]);
            });

        dump("Mass updated all filters of each view filters based on discussed object structure.");
    }
}
