<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Suppliers\Support\SupplierIntegrationRegistry;

class SupplierIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierIntegrationRegistry::class, function ($app) {
            $integrations = array_map(
                fn (string $class) => $app->make($class),
                array_keys((array) config('suppliers.integrations', [])),
            );

            return new SupplierIntegrationRegistry($integrations);
        });
    }
}
