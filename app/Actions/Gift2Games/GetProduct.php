<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class GetProduct
{
    public function __construct(private ClientFactory $clientFactory) {}

    /**
     * Fetch a single product from Gift2Games by its product id.
     *
     * @return array<string, mixed>|null The matching product, or null when it cannot be resolved.
     */
    public function execute(string $supplierSlug, int|string $productId): ?array
    {
        $response = $this->clientFactory->make($supplierSlug)->getProduct($productId);

        if (! ($response['status'] ?? false)) {
            return null;
        }

        foreach ($response['data'] ?? [] as $product) {
            if ((string) ($product['id'] ?? '') === (string) $productId) {
                return $product;
            }
        }

        return null;
    }
}
