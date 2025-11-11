<?php

namespace App\Repositories;

use App\Actions\Ezcards\PlaceOrder as EzcardsPlaceOrder;
use App\Actions\Gift2Games\CreateOrder as Gift2GamesCreateOrder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Ramsey\Uuid\Uuid;
use App\Models\Voucher;

class PurchaseOrderRepository
{
    public function __construct(
        private EzcardsPlaceOrder $ezcardsPlaceOrder,
        private Gift2GamesCreateOrder $gift2GamesCreateOrder
    ) {}
    /**
     * Get paginated purchase orders filtered by the provided criteria.
     * @param array $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getFilteredPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['product', 'supplier']);

        if (!empty($filters['supplier_name'])) {
            $query->whereHas('supplier', fn($q) => $q->where('name', 'like', '%' . $filters['supplier_name'] . '%'));
        }

        if (!empty($filters['product_name'])) {
            $query->whereHas('product', fn($q) => $q->where('name', 'like', '%' . $filters['product_name'] . '%'));
        }

        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Create a new purchase order.
     * @return PurchaseOrder
     *
     * @throws \RuntimeException
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        // Fetch supplier and product
        /** @var Supplier $supplier */
        $supplier = Supplier::findOrFail($data['supplier_id']);
        /** @var Product $product */
        $product = Product::findOrFail($data['product_id']);

        // Generate unique order number
        $orderNumber = Uuid::uuid4()->toString();

        $placeOrderData = [
            'product_sku' => $product->sku,
            'quantity'    => $data['quantity'],
            'order_number' => $orderNumber,
        ];

        $orderResponse = null;

        try {
            switch ($supplier->slug) {
                case 'ez_cards':
                    $orderResponse = $this->ezcardsPlaceOrder->execute($placeOrderData);
                    $data['transaction_id'] = $orderResponse['data']['transactionId'] ?? null;
                    break;

                case 'gift2games':
                    $orderResponse = $this->gift2GamesCreateOrder->execute($placeOrderData);
                    break;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to place order with supplier {$supplier->slug}: " . $e->getMessage(), 0, $e);
        }

        $data['order_number'] = $orderNumber;
        $data['total_price'] = $product->purchase_price * $data['quantity'];

        $purchase_order = PurchaseOrder::create($data);

        if ($supplier->slug === 'gift2games') {
            Voucher::create([
                'purchase_order_id' => $purchase_order->id,
                'code' => $orderResponse['data']['serialCode'],
                'serial_number' => $orderResponse['data']['serialNumber'],
                'status' => 'COMPLETED',
            ]);
        }

        return $purchase_order;
    }
}
