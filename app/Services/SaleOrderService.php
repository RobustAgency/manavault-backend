<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrderStatus;
use Illuminate\Support\Facades\DB;
use App\Repositories\ProductRepository;
use App\Repositories\SaleOrderRepository;

class SaleOrderService
{
    public function __construct(
        private ProductRepository $productRepository,
        private SaleOrderRepository $saleOrderRepository,
        private AutoPurchaseOrderService $autoPurchaseOrderService,
        private DigitalProductAllocationService $digitalProductAllocationService,
    ) {}

    public function createOrder(array $data): SaleOrder
    {
        return DB::transaction(function () use ($data) {
            $existingOrder = $this->saleOrderRepository->getSaleOrderByOrderNumber($data['order_number']);

            // If the order already exists with its items, it has already been processed; return it as-is.
            if ($existingOrder !== null && $existingOrder->items()->exists()) {
                logger()->info("Sale order with order number {$data['order_number']} already exists with items (ID: {$existingOrder->id}); skipping creation.");

                return $existingOrder->load(['items.digitalProducts']);
            }

            // Reuse an order that was created but never had its items populated; otherwise create a fresh one.
            $saleOrder = $existingOrder ?? $this->saleOrderRepository->createSaleOrder([
                'order_number' => $data['order_number'],
                'source' => SaleOrder::MANASTORE,
                'total_price' => 0,
                'status' => SaleOrderStatus::PENDING->value,
            ]);

            logger()->info(($existingOrder !== null ? 'Populating items for existing' : 'Creating')." sale order with ID: {$saleOrder->id} and order number: {$saleOrder->order_number}");

            $totalPrice = 0;
            $shortfalls = [];

            $saleOrder->update(['status' => SaleOrderStatus::PROCESSING->value]);

            logger()->info("Processing sale order ID: {$saleOrder->id} with status set to PROCESSING");

            foreach ($data['items'] as $itemData) {
                $product = $this->productRepository->getProductById($itemData['product_id']);
                $quantity = $itemData['quantity'];

                $unitPrice = $product->selling_price;
                $subtotal = $quantity * $unitPrice; // FIXME: Use money package here.

                // Resolve the supplier digital product once and persist it on the item
                $digitalProduct = $product->digitalProduct();

                $item = $saleOrder->items()->create([
                    'product_id' => $product->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $allocated = $this->digitalProductAllocationService->allocateFromGeneralStock($item, $digitalProduct, $quantity);

                if ($allocated < $quantity) {
                    $shortfalls[] = [
                        'item' => $item,
                        'digitalProduct' => $digitalProduct,
                        'remaining' => $quantity - $allocated,
                    ];
                }

                $totalPrice += $subtotal;
            }

            // Create auto-POs for remaining shortfalls after exhausting general stock.
            foreach ($shortfalls as ['digitalProduct' => $digitalProduct, 'remaining' => $remaining]) {
                if ($digitalProduct !== null) {
                    $this->autoPurchaseOrderService->handleShortfall($digitalProduct, $remaining, $saleOrder->id);
                }
            }

            // Refresh to pick up any allocations made synchronously by the ProcessVoucherCodes
            // listener (e.g. when the queue driver is sync and the supplier returns vouchers immediately).
            $saleOrder->refresh();

            // Resolve the status from actual allocations and persist it alongside the total.
            // The status change fires SaleOrderUpdated, which dispatches SaleOrderFulfillmentUpdated
            // via the DispatchSaleOrderStatusEvents listener.
            $status = $this->resolveStatus($saleOrder);
            $saleOrder->update(['status' => $status->value, 'total_price' => $totalPrice]);

            return $saleOrder->load(['items.digitalProducts']);
        });
    }

    /**
     * Resolve the sale order status from allocations and persist it.
     */
    public function updateStatus(SaleOrder $saleOrder): SaleOrderStatus
    {
        $status = $this->resolveStatus($saleOrder);

        $saleOrder->update(['status' => $status->value]);

        return $status;
    }

    /**
     * Determine the sale order status from how much of each item has been allocated.
     *
     * Shortfall purchase orders are created per item, so an arriving batch fully
     * allocates the item it was placed for. The order is COMPLETED once every item
     * is fully allocated, PARTIALLY_FULFILLED while at least one item is, and
     * otherwise left PROCESSING (awaiting more stock).
     */
    public function resolveStatus(SaleOrder $saleOrder): SaleOrderStatus
    {
        $saleOrder->load('items.digitalProducts');

        $items = $saleOrder->items;
        $fullyAllocated = $items->filter(
            fn (SaleOrderItem $item): bool => $item->digitalProducts->count() >= $item->quantity
        );

        if ($fullyAllocated->count() === $items->count()) {
            return SaleOrderStatus::COMPLETED;
        }

        if ($fullyAllocated->isNotEmpty()) {
            return SaleOrderStatus::PARTIALLY_FULFILLED;
        }

        return SaleOrderStatus::PROCESSING;
    }
}
