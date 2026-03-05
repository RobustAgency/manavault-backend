<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
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
        $currency = $data['currency'] ?? 'usd';
        $groupedData = $this->groupBySupplierIdService->groupBySupplierId($data['items']);
        $orderNumber = $this->generateOrderNumber();

        DB::beginTransaction();
        try {
            $purchaseOrder = PurchaseOrder::create([
                'total_price' => 0,
                'order_number' => $orderNumber,
                'status' => PurchaseOrderStatus::PROCESSING->value,
                'currency' => $currency,
            ]);

            foreach ($groupedData as $supplierOrderData) {
                $supplierId = (int) $supplierOrderData['supplier_id'];
                $items = $supplierOrderData['items'];
                $supplier = Supplier::findOrFail($supplierId);
                $isInternal = ! $this->isExternalSupplier($supplier);

                try {
                    $this->processPurchaseOrderItems($purchaseOrder, $supplier, $items, $orderNumber, $currency);
                } catch (\Exception $e) {
                    // Internal suppliers are never marked as failed — they are handled manually.
                    // For external suppliers, record the failure and continue with the remaining suppliers.
                    if ($isInternal) {
                        Log::warning('Internal supplier processing failed, skipping failure status', [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'purchase_order_id' => $purchaseOrder->id,
                            'error' => $e->getMessage(),
                        ]);
                    } else {
                        Log::error('External supplier processing failed', [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'purchase_order_id' => $purchaseOrder->id,
                            'error' => $e->getMessage(),
                        ]);

                        // Create (or update) the PurchaseOrderSupplier record with a failed status
                        PurchaseOrderSupplier::updateOrCreate(
                            [
                                'purchase_order_id' => $purchaseOrder->id,
                                'supplier_id' => $supplier->id,
                            ],
                            ['status' => PurchaseOrderSupplierStatus::FAILED->value]
                        );
                    }
                }
            }

            // Update the overall purchase order status based on all supplier statuses
            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

            DB::commit();

            return $purchaseOrder->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Failed to create purchase order: '.$e->getMessage());
        }
    }

    private function processPurchaseOrderItems(PurchaseOrder $purchaseOrder, Supplier $supplier, array $items, string $orderNumber, string $currency): void
    {
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
                $externalOrderResponse = $this->placeExternalOrder($supplier, $orderItems, $orderNumber, $currency);
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
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        // Create purchase order items
        foreach ($orderItems as $orderItem) {
            /** @var DigitalProduct $digitalProduct */
            $digitalProduct = $orderItem['digital_product'];
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'digital_product_id' => $orderItem['digital_product_id'],
                'product_name' => $digitalProduct->name,
                'product_sku' => $digitalProduct->sku,
                'product_brand' => $digitalProduct->brand,
                'quantity' => $orderItem['quantity'],
                'unit_cost' => $orderItem['unit_cost'],
                'subtotal' => $orderItem['subtotal'],
            ]);
        }

        if ($this->isGift2GamesSupplier($supplier)) {
            // For Gift2Games, vouchers are returned immediately in the order response.
            // Store them now; updateStatus (called after all suppliers are processed) will
            // promote this supplier to "completed" once the voucher count matches.
            $this->gift2GamesVoucherService->storeVouchers($purchaseOrder, $externalOrderResponse);
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

    private function generateOrderNumber(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    private function isExternalSupplier(Supplier $supplier): bool
    {
        return $supplier->type === 'external';
    }

    private function isGift2GamesSupplier(Supplier $supplier): bool
    {
        return str_starts_with($supplier->slug, 'gift2games')
            || str_starts_with($supplier->slug, 'gift-2-games');
    }

    private function placeExternalOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency): array
    {
        if ($supplier->slug === 'ez_cards') {
            return $this->ezcardPlaceOrderService->placeOrder($orderItems, $orderNumber, $currency);
        }

        if ($this->isGift2GamesSupplier($supplier)) {
            return $this->gift2GamesPlaceOrderService->placeOrder($orderItems, $orderNumber, $supplier->slug);
        }

        throw new \RuntimeException("Unknown external supplier: {$supplier->slug}");
    }

    public function getPurchaseOrderByID(int $purchaseOrderID): PurchaseOrder
    {
        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::with('items')->find($purchaseOrderID);

        if (! $purchaseOrder) {
            throw new \RuntimeException('Purchase order not found with ID: '.$purchaseOrderID);
        }

        return $purchaseOrder;
    }
}
