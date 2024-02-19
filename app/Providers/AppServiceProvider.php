<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $loader = AliasLoader::getInstance();
        $loader->alias(\Laravel\Passport\AuthCode::class,\App\Models\Passport\AuthCode::class);
        $loader->alias(\Laravel\Passport\Client::class,\App\Models\Passport\Client::class);
        $loader->alias(\Laravel\Passport\Token::class,\App\Models\Passport\Token::class);
        $loader->alias(\Laravel\Passport\PersonalAccessClient::class,\App\Models\Passport\PersonalAccessClient::class);
        $loader->alias(\Laravel\Passport\RefreshToken::class, \App\Models\Passport\RefreshToken::class);
    }
}
