<?php

namespace App\Repositories;

use App\Actions\Ezcards\PlaceOrder as EzcardsPlaceOrder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Ramsey\Uuid\Uuid;

class PurchaseOrderRepository
{
    public function __construct(private EzcardsPlaceOrder $ezcardsPlaceOrder) {}
    /**
     * Get paginated purchase orders filtered by the provided criteria.
     * @param array $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getPaginatedPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['product', 'supplier']);

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Create a new purchase order.
     * @param array $data
     * @return PurchaseOrder|array
     */
    public function createPurchaseOrder(array $data): PurchaseOrder|array
    {
        $supplier = Supplier::findOrFail($data['supplier_id']);
        $product = Product::findOrFail($data['product_id']);
        $uuid = Uuid::uuid4()->toString();


        $placeOrderData = [
            'product_sku' => $product->sku,
            'quantity' => $data['quantity'],
            'order_number' => $uuid,
        ];

        try {
            switch ($supplier->slug) {
                case 'ez_cards':
                    $this->ezcardsPlaceOrder->execute($placeOrderData);
                    break;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $data['order_number'] = $uuid;
        $data['total_price'] = $product->purchase_price * $data['quantity'];

        return PurchaseOrder::create($data);
    }
}
