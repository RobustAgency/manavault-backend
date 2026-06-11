<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Integrations\EzCards;
use App\Integrations\Giftery;
use App\Integrations\Tikkery;
use App\Integrations\Gift2GamesEur;
use App\Integrations\Gift2GamesGbp;
use App\Integrations\Gift2GamesUsd;
use App\Contracts\SupplierIntegrationContract;

class SupplierIntegrationResolver
{
    public function __construct(
        private readonly EzCards $ezCardsIntegration,
        private readonly Giftery $gifteryIntegration,
        private readonly Tikkery $tikkeryIntegration,
        private readonly Gift2GamesUsd $gift2GamesUsdIntegration,
        private readonly Gift2GamesEur $gift2GamesEurIntegration,
        private readonly Gift2GamesGbp $gift2GamesGbpIntegration,
    ) {}

    public function resolve(Supplier $supplier): ?SupplierIntegrationContract
    {
        return match ($supplier->slug) {
            'ez_cards' => $this->ezCardsIntegration,
            'giftery-api' => $this->gifteryIntegration,
            'tikkery' => $this->tikkeryIntegration,
            'gift2games' => $this->gift2GamesUsdIntegration,
            'gift-2-games-eur' => $this->gift2GamesEurIntegration,
            'gift-2-games-gbp' => $this->gift2GamesGbpIntegration,
            default => null,
        };
    }
}
