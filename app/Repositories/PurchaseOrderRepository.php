<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Ezcards\EzcardsPlaceOrderService;
use App\Services\Gift2Games\Gift2GamesVoucherService;
use App\Services\Gift2Games\Gift2GamesPlaceOrderService;

class PurchaseOrderRepository
{
    public function __construct(
        private EzcardsPlaceOrderService $ezcardPlaceOrderService,
        private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService,
        private Gift2GamesVoucherService $gift2GamesVoucherService,
    ) {}

    /**
     * Get paginated purchase orders filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getFilteredPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['items', 'supplier', 'vouchers']);

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        /** @var int $supplierId */
        $supplierId = $data['supplier_id'];

        /** @var array<int, array{digital_product_id:int, quantity:int}> $items */
        $items = $data['items'];

        $supplier = Supplier::findOrFail($supplierId);
        $orderNumber = $this->generateOrderNumber();
        $status = 'completed';

        DB::beginTransaction();
        try {
            $totalPrice = 0;
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
                        'supplier_id' => $supplierId,
                        'transaction_id' => $transactionId,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to place external order', [
                        'supplier_slug' => $supplier->slug,
                        'supplier_id' => $supplierId,
                        'error' => $e->getMessage(),
                    ]);
                    $status = 'failed';
                }
            }

            // Create a single purchase order
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $supplierId,
                'total_price' => $totalPrice,
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'status' => $status,
            ]);

            // Create purchase order items
            foreach ($orderItems as $orderItem) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'digital_product_id' => $orderItem['digital_product_id'],
                    'quantity' => $orderItem['quantity'],
                    'unit_cost' => $orderItem['unit_cost'],
                    'subtotal' => $orderItem['subtotal'],
                ]);
            }

            $purchaseOrder->load('items.digitalProduct');

            if ($supplier->slug === 'gift2games') {
                // For Gift2Games, vouchers are returned immediately in the order response
                // Use the Gift2GamesVoucherService to process and store them
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

            DB::commit();

            return $purchaseOrder;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Failed to create purchase order: '.$e->getMessage());
        }
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
