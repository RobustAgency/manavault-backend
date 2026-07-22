<?php

namespace App\Clients\ManaStore;

use App\Clients\BaseApiClient;

class Client extends BaseApiClient
{
    protected function getConfigPrefix(): string
    {
        return 'services.manastore';
    }

    protected function getServiceName(): string
    {
        return 'ManaStore';
    }

    /**
     * Fetch the customer attached to a sale order on ManaStore, keyed by order number.
     *
     * Returns the customer payload (external_id, name, email, company_name,
     * company_email) or null when the order or its customer cannot be resolved.
     *
     * @return array<string, mixed>|null
     */
    public function getSaleOrderCustomer(int|string $orderNumber): ?array
    {
        $response = $this->getClient()->get("api/v1/manavault/order/{$orderNumber}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new \Exception($this->getServiceName().' API request failed: '.$response->body());
        }

        return $response->json('data.customer') ?? $response->json('customer');
    }
}
