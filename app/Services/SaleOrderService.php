<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrder\Status;
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
            $saleOrder = $this->saleOrderRepository->createSaleOrder([
                'order_number' => $data['order_number'],
                'source' => SaleOrder::MANASTORE,
                'total_price' => 0,
                'status' => Status::PENDING->value,
            ]);

            logger()->info("Creating sale order with ID: {$saleOrder->id} and order number: {$saleOrder->order_number}");

            $totalPrice = 0;
            $shortfalls = [];

            $saleOrder->update(['status' => Status::PROCESSING->value]);

            logger()->info("Processing sale order ID: {$saleOrder->id} with status set to PROCESSING");

            foreach ($data['items'] as $itemData) {
                $product = $this->productRepository->getProductById($itemData['product_id']);
                $quantity = $itemData['quantity'];

                $unitPrice = $product->selling_price;
                $subtotal = $quantity * $unitPrice; // FIXME: Use money package here.

                $item = $saleOrder->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $allocated = $this->digitalProductAllocationService->allocateFromGeneralStock($item, $product, $quantity);

                if ($allocated < $quantity) {
                    $shortfalls[] = [
                        'item' => $item,
                        'product' => $product,
                        'remaining' => $quantity - $allocated,
                    ];
                }

                $totalPrice += $subtotal;
            }

            // Create auto-POs for remaining shortfalls after exhausting general stock.
            foreach ($shortfalls as ['product' => $product, 'remaining' => $remaining]) {
                $digitalProduct = $product->digitalProduct();
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
    public function updateStatus(SaleOrder $saleOrder): Status
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
    public function resolveStatus(SaleOrder $saleOrder): Status
    {
        $saleOrder->load('items.digitalProducts');

        $items = $saleOrder->items;
        $fullyAllocated = $items->filter(
            fn (SaleOrderItem $item): bool => $item->digitalProducts->count() >= $item->quantity
        );

        if ($fullyAllocated->count() === $items->count()) {
            return Status::COMPLETED;
        }

        if ($fullyAllocated->isNotEmpty()) {
            return Status::PARTIALLY_FULFILLED;
        }

        return Status::PROCESSING;
    }
}
