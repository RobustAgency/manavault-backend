<?php

namespace App\Services;

use App\Models\Supplier;
use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use App\Repositories\PurchaseOrderRepository;
use App\Services\PurchaseOrder\GroupBySupplierIdService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class PurchaseOrderService
{
    public function __construct(
        private GroupBySupplierIdService $groupBySupplierIdService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private PurchaseOrderRepository $purchaseOrderRepository,
    ) {}

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        $currency = $data['currency'] ?? 'usd';
        $grouped = $this->groupBySupplierIdService->groupBySupplierId($data['items']);
        $orderNumber = $this->generateOrderNumber();

        DB::beginTransaction();
        try {
            $purchaseOrder = $this->purchaseOrderRepository->createPurchaseOrder([
                'total_price' => 0,
                'order_number' => $orderNumber,
                'status' => PurchaseOrderStatus::PROCESSING->value,
                'currency' => $currency,
            ]);

            foreach ($grouped as $supplierOrderData) {
                $supplierId = (int) $supplierOrderData['supplier_id'];
                $items = $supplierOrderData['items'];
                $supplier = Supplier::findOrFail($supplierId);

                $this->processSupplierItems($purchaseOrder, $supplier, $items, $orderNumber, $currency);
            }

            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

            DB::commit();

            return $purchaseOrder;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Failed to create purchase order: '.$e->getMessage());
        }
    }

    private function processSupplierItems(
        PurchaseOrder $purchaseOrder,
        Supplier $supplier,
        array $items,
        string $orderNumber,
        string $currency,
    ): void {
        $totalPrice = (float) $purchaseOrder->total_price;
        $orderItems = [];

        foreach ($items as $item) {
            /** @var DigitalProduct $digitalProduct */
            $digitalProduct = DigitalProduct::findOrFail($item['digital_product_id']);
            $quantity = $item['quantity'];
            $unitCost = $digitalProduct->cost_price;
            $subtotal = $quantity * $unitCost;

            $totalPrice += $subtotal;

            $orderItems[] = [
                'digital_product_id' => $digitalProduct->id,
                'digital_product' => $digitalProduct,
                'quantity' => $quantity,
                'unit_cost' => (float) $unitCost,
                'subtotal' => (float) $subtotal,
            ];
        }

        $purchaseOrderSupplier = $this->purchaseOrderRepository->createPurchaseOrderSupplier([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        $purchaseOrderItems = [];
        foreach ($orderItems as $orderItem) {
            /** @var DigitalProduct $digitalProduct */
            $digitalProduct = $orderItem['digital_product'];
            $purchaseOrderItems[] = $this->purchaseOrderRepository->createPurchaseOrderItem([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'digital_product_id' => $orderItem['digital_product_id'],
                'digital_product_name' => $digitalProduct->name,
                'digital_product_sku' => $digitalProduct->sku,
                'digital_product_brand' => $digitalProduct->brand,
                'quantity' => $orderItem['quantity'],
                'unit_cost' => $orderItem['unit_cost'],
                'subtotal' => $orderItem['subtotal'],
            ]);
        }

        $purchaseOrder->update(['total_price' => $totalPrice]);

        if ($supplier->type === SupplierType::EXTERNAL->value) {
            PlaceExternalPurchaseOrderJob::dispatch(
                $purchaseOrder,
                $supplier,
                $purchaseOrderSupplier,
                $purchaseOrderItems,
                $orderNumber,
                $currency,
            );

            Log::info('External purchase order job dispatched', [
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_slug' => $supplier->slug,
                'supplier_id' => $supplier->id,
            ]);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    /**
     * Convenience wrapper to create a purchase order for a single digital product.
     * Used by AutoPurchaseOrderService when covering sale order shortfalls.
     */
    public function createPurchaseOrderForDigitalProduct(
        DigitalProduct $digitalProduct,
        int $quantity
    ): PurchaseOrder {
        return $this->createPurchaseOrder([
            'currency' => $digitalProduct->currency,
            'items' => [
                [
                    'supplier_id' => $digitalProduct->supplier_id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => $quantity,
                ],
            ],
        ]);
    }
}
