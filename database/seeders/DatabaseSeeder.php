<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Core\Entity;
use App\Models\Core\Relation;
use App\Models\User;
use App\Models\UserConfig;
use Illuminate\Database\Seeder;
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

        $this->changeRelationUserModelClass(false);
        $this->renameConnectionIdToConnectionIdsInEntityCollections();
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
}
