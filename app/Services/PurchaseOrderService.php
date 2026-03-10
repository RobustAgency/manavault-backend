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
use App\Repositories\PurchaseOrderRepository;
use App\Services\Gift2Games\Gift2GamesVoucherService;
use App\Services\PurchaseOrder\GroupBySupplierIdService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

class PurchaseOrderService
{
    public function __construct(
        private GroupBySupplierIdService $groupBySupplierIdService,
        private PurchaseOrderPlacementService $purchaseOrderPlacementService,
        private Gift2GamesVoucherService $gift2GamesVoucherService,
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
        $totalPrice = $purchaseOrder->total_price;
        $orderItems = [];
        $transactionId = null;
        $externalOrderResponse = [];

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

        if ($supplier->type === SupplierType::EXTERNAL->value) {
            try {
                $externalOrderResponse = $this->purchaseOrderPlacementService->placeOrder(
                    $supplier,
                    $orderItems,
                    $orderNumber,
                    $currency
                );
                $transactionId = $externalOrderResponse['transactionId'] ?? null;

                $purchaseOrderSupplier->update(['transaction_id' => $transactionId]);

                Log::info('External order placed successfully', [
                    'supplier_slug' => $supplier->slug,
                    'supplier_id' => $supplier->id,
                    'transaction_id' => $transactionId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to place external order', [
                    'supplier_slug' => $supplier->slug,
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);

                $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);

                return;
            }
        }

        foreach ($orderItems as $orderItem) {
            /** @var DigitalProduct $digitalProduct */
            $digitalProduct = $orderItem['digital_product'];
            $this->purchaseOrderRepository->createPurchaseOrderItem([
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

        if ($this->isGift2GamesSupplier($supplier)) {
            $this->gift2GamesVoucherService->storeVouchers($purchaseOrder, $externalOrderResponse);
            $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        } elseif ($supplier->slug === 'ez_cards') {
            Log::info('EzCards order created, vouchers will be fetched separately', [
                'purchase_order_id' => $purchaseOrder->id,
                'transaction_id' => $transactionId,
            ]);
        }

        $purchaseOrder->update(['total_price' => $totalPrice]);
    }

    private function isGift2GamesSupplier(Supplier $supplier): bool
    {
        return str_starts_with($supplier->slug, 'gift2games')
            || str_starts_with($supplier->slug, 'gift-2-games');
    }

    private function generateOrderNumber(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(uniqid());
    }
}
