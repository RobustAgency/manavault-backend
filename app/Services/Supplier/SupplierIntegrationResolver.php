<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Integrations\EzCards;
use App\Integrations\Gift2Games;
use App\Contracts\SupplierIntegrationContract;

class SupplierIntegrationResolver
{
    private const G2G_SLUGS = ['gift2games', 'gift-2-games-eur', 'gift-2-games-gbp'];

    public function __construct(
        private readonly EzCards $ezCardsIntegration,
    ) {}

    /**
     * Returns the new-style integration for the given supplier,
     * or null if the supplier should use the legacy order placement flow.
     */
    public function resolve(Supplier $supplier): ?SupplierIntegrationContract
    {
        if ($supplier->slug === 'ez_cards') {
            return $this->ezCardsIntegration;
        }

        if (in_array($supplier->slug, self::G2G_SLUGS, strict: true)) {
            return app()->makeWith(Gift2Games::class, ['supplierSlug' => $supplier->slug]);
        }

        return null;
    }
}
