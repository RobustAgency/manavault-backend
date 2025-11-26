<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Ezcards\EzcardsPlaceOrderService;
use App\Services\Gift2Games\Gift2GamesVoucherService;
use App\Services\Gift2Games\Gift2GamesPlaceOrderService;
use App\Services\PurchaseOrder\GroupBySupplierIdService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class PurchaseOrderRepository
{
    public function __construct(
        private EzcardsPlaceOrderService $ezcardPlaceOrderService,
        private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService,
        private Gift2GamesVoucherService $gift2GamesVoucherService,
        private GroupBySupplierIdService $groupBySupplierIdService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    /**
     * Get paginated purchase orders filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getFilteredPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['items', 'suppliers', 'vouchers']);

        if (isset($filters['supplier_id'])) {
            $query->whereHas('purchaseOrderSuppliers', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['order_number'])) {
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        $data = $this->groupBySupplierIdService->groupBySupplierId($data['items']);
        $orderNumber = $this->generateOrderNumber();

        DB::beginTransaction();
        try {
            $purchaseOrder = PurchaseOrder::create([
                'total_price' => 0,
                'order_number' => $orderNumber,
                'status' => 'completed',
            ]);

            foreach ($data as $supplierOrderData) {
                $supplierId = (int) $supplierOrderData['supplier_id'];
                $items = $supplierOrderData['items'];
                $supplier = Supplier::findOrFail($supplierId);

                $this->processPurchaseOrderItems($purchaseOrder, $supplier, $items, $orderNumber);
            }

            // Update the overall purchase order status based on all suppliers
            $this->updatePurchaseOrderStatus($purchaseOrder);

            DB::commit();

            return $purchaseOrder;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Failed to create purchase order: '.$e->getMessage());
        }
    }

    private function processPurchaseOrderItems(PurchaseOrder $purchaseOrder, Supplier $supplier, array $items, string $orderNumber): void
    {
        $status = 'completed';
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

        if ($this->isExternalSupplier($supplier)) {
            try {
                $status = 'processing';
                $externalOrderResponse = $this->placeExternalOrder($supplier, $orderItems, $orderNumber);
                $transactionId = $externalOrderResponse['transactionId'] ?? null;

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
                $productName = isset($orderItems[0]['digital_product']) ? $orderItems[0]['digital_product']->name : 'Unknown Product';
                throw new \RuntimeException('Can not create purchase order against '.$supplier->name.' '.$productName);
            }
        }

        $purchaseOrderSupplier = PurchaseOrderSupplier::create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);

        // Create purchase order items
        foreach ($orderItems as $orderItem) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'digital_product_id' => $orderItem['digital_product_id'],
                'quantity' => $orderItem['quantity'],
                'unit_cost' => $orderItem['unit_cost'],
                'subtotal' => $orderItem['subtotal'],
            ]);
        }

        if ($supplier->slug === 'gift2games') {
            // For Gift2Games, vouchers are returned immediately in the order response
            // Use the Gift2GamesVoucherService to process and store them
            $this->gift2GamesVoucherService->storeVouchers($purchaseOrder, $externalOrderResponse);
            $status = 'completed';

            // Update the supplier status to completed after vouchers are stored
            $purchaseOrderSupplier->update(['status' => $status]);
        } elseif ($supplier->slug === 'ez_cards') {
            // For EzCards, vouchers are NOT returned immediately
            // They will be fetched later using EzcardsVoucherCodeService with the transaction_id
            // The status remains 'processing' until vouchers are fetched
            Log::info('EzCards order created, vouchers will be fetched separately', [
                'purchase_order_id' => $purchaseOrder->id,
                'transaction_id' => $transactionId,
            ]);
        }

        // Update the total price incrementally
        $purchaseOrder->update([
            'total_price' => $totalPrice,
        ]);
    }

    /**
     * Update the overall purchase order status based on all supplier statuses.
     */
    private function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
    }

    private function generateOrderNumber(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    private function isExternalSupplier(Supplier $supplier): bool
    {
        return $supplier->type === 'external';
    }

    private function placeExternalOrder(Supplier $supplier, array $orderItems, string $orderNumber): array
    {
        return match ($supplier->slug) {
            'ez_cards' => $this->ezcardPlaceOrderService->placeOrder($orderItems, $orderNumber),
            'gift2games' => $this->gift2GamesPlaceOrderService->placeOrder($orderItems, $orderNumber),
            default => throw new \RuntimeException("Unknown external supplier: {$supplier->slug}"),
        };
    }
}
