<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Ezcards\EzcardsPlaceOrderService;
use App\Services\Gift2Games\Gift2GamesPlaceOrderService;

class PurchaseOrderRepository
{
    public function __construct(
        private EzcardsPlaceOrderService $ezcardPlaceOrderService,
        private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService,
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
                if (! empty($externalOrderResponse)) {
                    $voucherCountByProduct = [];

                    foreach ($externalOrderResponse as $voucherData) {
                        $digitalProductId = $voucherData['digital_product_id'] ?? null;

                        if (! $digitalProductId) {
                            Log::warning('Gift2Games voucher missing digital_product_id', ['voucher' => $voucherData]);

                            continue;
                        }

                        // Initialize counter for this product if not exists
                        if (! isset($voucherCountByProduct[$digitalProductId])) {
                            $voucherCountByProduct[$digitalProductId] = 0;
                        }

                        // Find the purchase order item for this digital product
                        $purchaseOrderItem = $purchaseOrder->items->first(function ($item) use ($digitalProductId) {
                            return $item->digital_product_id == $digitalProductId;
                        });

                        if (! $purchaseOrderItem) {
                            Log::warning('No purchase order item found for voucher', [
                                'digital_product_id' => $digitalProductId,
                                'voucher' => $voucherData,
                            ]);

                            continue;
                        }

                        // Increment the counter
                        $voucherCountByProduct[$digitalProductId]++;

                        // Verify we're not exceeding the ordered quantity
                        if ($voucherCountByProduct[$digitalProductId] > $purchaseOrderItem->quantity) {
                            Log::warning('Received more vouchers than ordered quantity', [
                                'digital_product_id' => $digitalProductId,
                                'ordered_quantity' => $purchaseOrderItem->quantity,
                                'received_count' => $voucherCountByProduct[$digitalProductId],
                            ]);
                        }

                        Voucher::create([
                            'purchase_order_id' => $purchaseOrder->id,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'code' => $voucherData['serialCode'] ?? $voucherData['code'] ?? null,
                            'serial_number' => $voucherData['serialNumber'] ?? null,
                            'status' => 'available',
                        ]);
                    }

                    // Check if all vouchers were created successfully
                    $totalExpectedVouchers = $purchaseOrder->getTotalQuantity();
                    $totalCreatedVouchers = Voucher::where('purchase_order_id', $purchaseOrder->id)->count();

                    if ($totalCreatedVouchers >= $totalExpectedVouchers) {
                        $purchaseOrder->update([
                            'status' => 'completed',
                        ]);
                    }
                }
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
        return in_array($supplier->slug, ['ez_cards', 'gift2games']);
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
