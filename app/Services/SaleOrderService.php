<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use App\Repositories\ProductRepository;
use App\Repositories\SaleOrderRepository;

class SaleOrderService
{
    public function __construct(
        private ProductRepository $productRepository,
        private SaleOrderRepository $saleOrderRepository,
        private VoucherAllocationService $voucherAllocationService,
        private AutoPurchaseOrderService $autoPurchaseOrderService,
        private DigitalProductAllocationService $digitalProductAllocationService,
    ) {}

    public function createOrder(array $data): SaleOrder
    {
        $saleOrder = $this->saleOrderRepository->createSaleOrder([
            'order_number' => $data['order_number'],
            'source' => SaleOrder::MANASTORE,
            'currency' => $data['currency'],
            'subtotal' => $data['subtotal'],
            'conversion_fees' => $data['conversion_fees'],
            'total' => $data['total'],
            'total_price' => $data['total'] / 100,
            'status' => Status::PENDING->value,
        ]);

        logger()->info("Creating sale order with ID: {$saleOrder->id} and order number: {$saleOrder->order_number}");

        try {
            $this->validateProductsAndDigitalStock($data['items']);

            $this->triggerAutoPurchaseOrdersIfNeeded($data['items']);

            $fullyAllocated = true;

            $saleOrder->update(['status' => Status::PROCESSING->value]);

            logger()->info("Processing sale order ID: {$saleOrder->id} with status set to PROCESSING");
            foreach ($data['items'] as $itemData) {
                $product = $this->productRepository->getProductById($itemData['product_id']);
                $quantity = $itemData['quantity'];

                $unitPrice = $itemData['price'] / 100;
                if ($itemData['discount_amount'] > 0) {
                    $discountPerUnit = $itemData['discount_amount'] / $quantity;
                    $itemData['price'] = $itemData['price'] - $discountPerUnit;
                }

                $item = $saleOrder->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $itemData['price'] / 100,
                    'subtotal' => $itemData['total_price'] / 100,
                    'price' => $itemData['price'],
                    'purchase_price' => $itemData['purchase_price'],
                    'conversion_fee' => $itemData['conversion_fee'],
                    'total_price' => $itemData['total_price'],
                    'discount_amount' => $itemData['discount_amount'],
                    'currency' => $itemData['currency'],
                    'status' => 'pending',
                ]);

                $allocated = $this->digitalProductAllocationService->allocate($item, $product, $quantity);

                if ($allocated < $quantity) {
                    $fullyAllocated = false;
                }
            }

            $finalStatus = $fullyAllocated ? Status::COMPLETED->value : Status::PROCESSING->value;
            $saleOrder->update([
                'status' => $finalStatus,
            ]);

            if ($fullyAllocated) {
                event(new SaleOrderCompleted($saleOrder));
            }

            return $saleOrder->load(['items.digitalProducts']);

        } catch (\Exception $e) {
            logger()->error("Error processing sale order ID: {$saleOrder->id} - {$e->getMessage()}");

            return $saleOrder;
        }
    }

    /**
     * Validate products have digital products assigned
     */
    private function validateProductsAndDigitalStock(array $items): void
    {
        foreach ($items as $itemData) {
            $product = $this->productRepository->getProductById($itemData['product_id']);
            if ($product->digitalProducts->isEmpty()) {
                throw new \Exception("Product {$product->name} has no digital products assigned.");
            }
        }
    }

    /**
     * For items where stock is short, dispatch auto-POs via eligible external suppliers.
     */
    private function triggerAutoPurchaseOrdersIfNeeded(array $items): void
    {
        foreach ($items as $itemData) {
            $product = $this->productRepository->getProductById($itemData['product_id']);
            $quantity = $itemData['quantity'];
            $digitalProduct = $product->digitalProduct();
            $totalAvailable = $this->voucherAllocationService->getAvailableQuantity($digitalProduct->id);

            if ($totalAvailable < $quantity) {
                $shortfall = $quantity - $totalAvailable;
                $this->autoPurchaseOrderService->handleShortfall($digitalProduct, $shortfall);
            }
        }
    }
}
