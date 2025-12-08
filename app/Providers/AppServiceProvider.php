<?php

namespace App\Providers;

use App\Models\Product;
use App\Clients\SupabaseClient;
use App\Observers\ProductObserver;
use App\Services\Auth\SupabaseGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SupabaseClient::class, function ($app) {
            return new SupabaseClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Supabase guard
        Auth::extend('supabase', function ($app, $name, array $config) {
            return new SupabaseGuard(
                $name,
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $app->make(SupabaseClient::class)
            );
        });

        // Register product model observer
        Product::observe(ProductObserver::class);
    }
}
