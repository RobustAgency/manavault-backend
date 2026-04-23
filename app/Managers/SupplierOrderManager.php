<?php

namespace App\Managers;

use Illuminate\Support\Manager;
use App\Suppliers\EzcardsOrderHandler;
use App\Contracts\Suppliers\WebhookSupplierInterface;
use App\Contracts\Suppliers\PollableSupplierInterface;

class SupplierOrderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'ez_cards';
    }

    // ── Driver factory methods ──────────────────────────────────────
    // Each method follows the naming convention: createXxxDriver()
    // where "Xxx" is the slug with underscores removed and first letter
    // of each word capitalised (Laravel Manager convention).
    // ----------------------------------------------------------------

    protected function createEzCardsDriver(): EzcardsOrderHandler
    {
        return $this->container->make(EzcardsOrderHandler::class);
    }

    /**
     * Returns true if a handler is registered for the given slug.
     */
    public function supports(string $slug): bool
    {
        return in_array($slug, $this->getRegisteredSlugs(), true);
    }

    // ── Filtered driver collections ────────────────────────────────

    /**
     * @return array<string, PollableSupplierInterface>
     */
    public function getPollableDrivers(): array
    {
        return collect($this->getRegisteredSlugs())
            ->mapWithKeys(fn (string $slug) => [$slug => $this->driver($slug)])
            ->filter(fn ($driver) => $driver instanceof PollableSupplierInterface)
            ->all();
    }

    /**
     * @return array<string, WebhookSupplierInterface>
     */
    public function getWebhookDrivers(): array
    {
        return collect($this->getRegisteredSlugs())
            ->mapWithKeys(fn (string $slug) => [$slug => $this->driver($slug)])
            ->filter(fn ($driver) => $driver instanceof WebhookSupplierInterface)
            ->all();
    }

    /**
     * All registered slugs. Must stay in sync with createXxxDriver() methods.
     *
     * @return string[]
     */
    private function getRegisteredSlugs(): array
    {
        return [
            'ez_cards',
            // 'gift2games',
            // 'gift-2-games',
            // 'giftery-api',
            // 'irewardify',
            // 'tikkery',
        ];
    }
}
