<?php

namespace App\Services\Supplier;

use App\Contracts\SupplierIntegrationContract;
use App\Integrations\Gift2Games\Gift2GamesIntegration;
use App\Models\Supplier;

class SupplierIntegrationResolver
{
    private const G2G_SLUGS = [
        'gift2games',
        'gift-2-games-eur',
        'gift-2-games-gbp',
    ];

    public function __construct(
        private readonly Gift2GamesIntegration $gift2GamesIntegration,
    ) {}

    /**
     * Returns the new-style integration for the given supplier,
     * or null if the supplier should use the legacy order placement flow.
     */
    public function resolve(Supplier $supplier): ?SupplierIntegrationContract
    {
        if (in_array($supplier->slug, self::G2G_SLUGS, true)) {
            return $this->gift2GamesIntegration;
        }

        return null;
    }
}
