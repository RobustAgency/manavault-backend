<?php

namespace App\Integrations\EzCards;

use App\Models\PurchaseOrder;
use App\Actions\Ezcards\PlaceOrder;
use Illuminate\Support\Facades\Log;
use App\Actions\Ezcards\GetVoucherCodes;
use App\Services\Ezcards\SyncDigitalProduct;
use App\Contracts\SupplierIntegrationContract;

class EzCards implements SupplierIntegrationContract
{
    public function __construct(
        private readonly PlaceOrder $placeOrderAction,
        private readonly GetVoucherCodes $getVoucherCodesAction,
        private readonly SyncDigitalProduct $syncDigitalProduct,
    ) {}

    public function placeOrder(array $orderItems, string $orderNumber, string $currency, PurchaseOrder $purchaseOrder): array
    {
        $products = [];
        foreach ($orderItems as $item) {
            $products[] = [
                'sku' => $item->digitalProduct->sku,
                'quantity' => $item->quantity,
            ];
        }

        $response = $this->placeOrderAction->execute([
            'clientOrderNumber' => $orderNumber,
            'products' => $products,
            'payWithCurrency' => strtoupper($currency),
        ]);

        Log::info('EzCards order placed', [
            'order_number' => $orderNumber,
            'response' => $response,
        ]);

        return $response['data'] ?? [];
    }

    public function fetchVouchers(string $transactionId, PurchaseOrder $purchaseOrder): array
    {
        $response = $this->getVoucherCodesAction->execute((int) $transactionId);

        return $response['data'] ?? [];
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProduct->processSyncAllProducts();
    }
}
