<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
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
                $subtotal = $quantity * $unitPrice;

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

            // Refresh to pick up any status changes made synchronously by the ProcessVoucherCodes
            // listener (e.g. when the queue driver is sync and the supplier returns vouchers immediately).
            $saleOrder->refresh();

            if ($saleOrder->status !== Status::COMPLETED->value) {
                $fullyAllocated = empty($shortfalls);
                $finalStatus = $fullyAllocated ? Status::COMPLETED->value : Status::PROCESSING->value;
                $saleOrder->update(['status' => $finalStatus, 'total_price' => $totalPrice]);

                if ($fullyAllocated) {
                    event(new SaleOrderCompleted($saleOrder));
                }
            } else {
                $saleOrder->update(['total_price' => $totalPrice]);
            }

            return $saleOrder->load(['items.digitalProducts']);
        });
    }
}
