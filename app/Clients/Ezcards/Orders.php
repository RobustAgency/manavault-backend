<?php

namespace App\Clients\Ezcards;

class Orders extends Client
{
    public function placeOrder(array $orderData): array
    {
        $validated = $this->validateOrderData($orderData);
        $response = $this->getClient()->post('/v2/orders', $validated);

        return $this->handleResponse($response);
    }

    /**
     * Validate order data according to EZ Cards API specifications.
     *
     * @param  array  $orderData  The order data to validate
     * @return array The validated order data
     *
     * @throws \InvalidArgumentException
     */
    private function validateOrderData(array $orderData): array
    {
        $validated = [];
        if (isset($orderData['clientOrderNumber'])) {
            if (! is_string($orderData['clientOrderNumber'])) {
                throw new \InvalidArgumentException('The "clientOrderNumber" must be a string.');
            }
            $validated['clientOrderNumber'] = $orderData['clientOrderNumber'];
        }

        if (isset($orderData['enableClientOrderNumberDupCheck'])) {
            if (! is_bool($orderData['enableClientOrderNumberDupCheck'])) {
                throw new \InvalidArgumentException('The "enableClientOrderNumberDupCheck" must be a boolean.');
            }
            $validated['enableClientOrderNumberDupCheck'] = $orderData['enableClientOrderNumberDupCheck'];
        } else {
            $validated['enableClientOrderNumberDupCheck'] = false;
        }

        if (! isset($orderData['products'])) {
            throw new \InvalidArgumentException('The "products" field is required.');
        }

        if (! is_array($orderData['products']) || empty($orderData['products'])) {
            throw new \InvalidArgumentException('The "products" must be a non-empty array.');
        }

        $validated['products'] = $this->validateProducts($orderData['products']);

        return $validated;
    }

    /**
     * Validate products array.
     *
     * @throws \InvalidArgumentException
     */
    private function validateProducts(array $products): array
    {
        $validated = [];

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                throw new \InvalidArgumentException("Product at index {$index} must be an array.");
            }

            // Add product validation logic here based on ProductRequest structure
            // This is a placeholder - adjust based on actual ProductRequest requirements
            $validated[] = $product;
        }

        return $validated;
    }
}
