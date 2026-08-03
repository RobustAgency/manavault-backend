<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\Supplier;
use App\Enums\VoucherCodeStatus;
use App\Clients\Gamezcode\Client;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\PurchaseOrderItemStatus;
use App\Events\DigitalProductsDeactivated;
use App\Contracts\SupplierIntegrationContract;
use App\Repositories\DigitalProductRepository;
use App\Services\Voucher\VoucherCipherService;

class Gamezcode implements SupplierIntegrationContract
{
    private const SUPPLIER_SLUG = 'gamezcode';

    private const PAGE_SIZE = 100;

    /**
     * Gamezcode (Kalixo) works in minor units (e.g. 10000 = 100.00).
     */
    private const MINOR_UNIT_DIVISOR = 100;

    /**
     * Kalixo order status returned once every ordered code has been delivered.
     */
    private const STATUS_COMPLETED = 'completed';

    private array $syncedSkus = [];

    public function __construct(
        private readonly Client $client,
        private readonly DigitalProductRepository $digitalProductRepository,
        private readonly VoucherCipherService $voucherCipherService,
    ) {}

    public function placeOrder(PurchaseOrderItem $purchaseOrderItem): void
    {
        if ($purchaseOrderItem->transaction_id) {
            Log::warning('Gamezcode placeOrder skipped: transaction_id already set', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
            ]);

            return;
        }

        $externalOrderCode = 'order_item_id_'.$purchaseOrderItem->id;

        $existingOrder = $this->client->getOrder($externalOrderCode);

        if ($existingOrder) {
            Log::warning('Gamezcode placeOrder skipped: order already exists for external code', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'external_order_code' => $externalOrderCode,
                'order_id' => $existingOrder['orderId'] ?? null,
            ]);

            $purchaseOrderItem->update([
                'transaction_id' => $existingOrder['orderId'] ?? null,
                'status' => PurchaseOrderItemStatus::PROCESSING,
            ]);

            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        $digitalProduct = $purchaseOrderItem->digitalProduct;
        $quantity = $purchaseOrderItem->quantity;
        $currency = strtoupper($purchaseOrder->currency);

        Log::info('Gamezcode placeOrder: creating order', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_code' => $digitalProduct->sku,
            'quantity' => $quantity,
        ]);

        $response = $this->client->placeOrder([
            'externalOrderCode' => $externalOrderCode,
            'currency' => $currency,
            'orderProducts' => [
                [
                    'productCode' => $digitalProduct->sku,
                    'quantity' => $quantity,
                ],
            ],
        ]);

        $orderId = $response['orderId'] ?? null;

        $purchaseOrderItem->update([
            'transaction_id' => $orderId,
            'status' => PurchaseOrderItemStatus::PROCESSING,
        ]);

        Log::info('Gamezcode placeOrder: order placed', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'transaction_id' => $orderId,
            'status' => $response['status'] ?? null,
        ]);
    }

    public function updateOrder(PurchaseOrderItem $purchaseOrderItem): void
    {
        if (! $purchaseOrderItem->transaction_id) {
            Log::warning('Gamezcode updateOrder skipped: no transaction_id', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
            ]);

            return;
        }

        $response = $this->client->getOrder($purchaseOrderItem->transaction_id);

        $status = $response['status'] ?? null;

        // Only store codes and fulfil once Kalixo reports the whole order complete.
        if ($status !== self::STATUS_COMPLETED) {
            Log::debug('Gamezcode updateOrder: order not completed yet', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
                'status' => $status,
            ]);

            return;
        }

        /** @var array<int, array<string, mixed>> $products */
        $products = $response['products'] ?? [];

        $codes = collect($products)
            ->flatMap(fn (array $product): array => $product['codes'] ?? [])
            ->pluck('code')
            ->filter()
            ->values();

        DB::transaction(function () use ($codes, $purchaseOrderItem): void {
            foreach ($codes as $code) {
                Voucher::create([
                    'code' => $this->voucherCipherService->encryptCode($code),
                    'purchase_order_id' => $purchaseOrderItem->purchase_order_id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'status' => VoucherCodeStatus::AVAILABLE->value,
                ]);
            }

            $purchaseOrderItem->update(['status' => PurchaseOrderItemStatus::FULFILLED]);
        });

        Log::info('Gamezcode updateOrder: order fulfilled', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'transaction_id' => $purchaseOrderItem->transaction_id,
            'voucher_count' => $codes->count(),
        ]);
    }

    public function syncProducts(): void
    {
        $supplier = Supplier::where('slug', self::SUPPLIER_SLUG)->firstOrFail();

        $skip = 0;

        do {
            $response = $this->client->getProducts(self::PAGE_SIZE, $skip);

            $total = $response['count'] ?? 0;
            $items = $response['products'] ?? [];

            if (empty($items)) {
                break;
            }

            $this->syncBatch($supplier, $items);

            $skip += count($items);
        } while ($skip < $total);

        $deactivatedIds = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $this->syncedSkus);

        if (! empty($deactivatedIds)) {
            Log::info('Gamezcode sync: deactivated '.count($deactivatedIds).' removed product(s)');
            event(new DigitalProductsDeactivated($deactivatedIds));
        }

        Log::info('Gamezcode sync completed. Synced '.count($this->syncedSkus).' digital products.');
    }

    /**
     * Sync a batch of products.
     */
    private function syncBatch(Supplier $supplier, array $items): void
    {
        foreach ($items as $item) {
            try {
                $sku = (string) $item['productCode'];

                $this->digitalProductRepository->createOrUpdate(
                    [
                        'sku' => $sku,
                        'supplier_id' => $supplier->id,
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'name' => $item['name'] ?? null,
                        'sku' => $sku,
                        'brand' => $item['brand'] ?? null,
                        'face_value' => isset($item['price']) ? $item['price'] / self::MINOR_UNIT_DIVISOR : null,
                        'cost_price' => isset($item['buyingPrice']) ? $item['buyingPrice'] / self::MINOR_UNIT_DIVISOR : null,
                        'currency' => strtolower($item['currencyCode'] ?? 'gbp'),
                        'region' => $item['countryCode'] ?? null,
                        'metadata' => $item,
                        'source' => 'api',
                        'last_synced_at' => now(),
                        'is_active' => ($item['status'] ?? null) === 'active',
                    ]
                );

                $this->syncedSkus[] = $sku;
            } catch (\Throwable $e) {
                Log::error("Gamezcode sync: failed to sync product {$item['productCode']}: {$e->getMessage()}");
            }
        }
    }
}
