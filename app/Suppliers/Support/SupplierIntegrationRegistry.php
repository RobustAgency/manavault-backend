<?php

namespace App\Suppliers\Support;

use App\Models\Supplier;
use App\Suppliers\Contracts\SupplierIntegration;

class SupplierIntegrationRegistry
{
    /**
     * @param  array<int, SupplierIntegration>  $integrations
     */
    public function __construct(private array $integrations) {}

    public function for(Supplier $supplier): SupplierIntegration
    {
        foreach ($this->integrations as $integration) {
            if ($integration->supports($supplier)) {
                return $integration;
            }
        }

        throw UnsupportedSupplierException::forSlug($supplier->slug);
    }

    public function has(Supplier $supplier): bool
    {
        foreach ($this->integrations as $integration) {
            if ($integration->supports($supplier)) {
                return true;
            }
        }

        return false;
    }
}
