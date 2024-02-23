<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use App\Models\UserConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $user = User::create([
            'name' => 'super admin',
            'email' => 'super@admin.com',
            'password' => Hash::make('superadmin'),
        ]);

        UserConfig::create([
            'user_id' => $user->_id,
            'app_theme' => 'esco',
            'app_theme_dark' => 'light',
            'app_theme_ripple' => true,
            'app_theme_input_style' => 'outlined',
            'app_theme_menu_type' => 'static',
            'app_theme_scale' => '14',
        ]);
    }
}
