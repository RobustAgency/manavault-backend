<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Contracts\SupplierIntegrationContract;
use App\Integrations\EzCards\EzCardsIntegration;

class SupplierIntegrationResolver
{
    public function __construct(
        private readonly EzCardsIntegration $ezCardsIntegration,
    ) {}

    /**
     * Returns the new-style integration for the given supplier,
     * or null if the supplier should use the legacy order placement flow.
     */
    public function resolve(Supplier $supplier): ?SupplierIntegrationContract
    {
        return match ($supplier->slug) {
            'ez_cards' => $this->ezCardsIntegration,
            default => null,
        };
    }
}
