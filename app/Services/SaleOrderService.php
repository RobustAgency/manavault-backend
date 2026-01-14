<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrder\Status;
use Illuminate\Support\Facades\DB;
use App\Enums\Product\FulfillmentMode;
use App\Repositories\ProductRepository;
use App\Repositories\SaleOrderRepository;
use App\Repositories\DigitalStockRepository;

class SaleOrderService
{
    public function __construct(
        private ProductRepository $productRepository,
        private SaleOrderRepository $saleOrderRepository,
        private DigitalStockRepository $digitalStockRepository
    ) {}

    public function createOrder(array $data): SaleOrder
    {
        DB::beginTransaction();

        try {
            $this->validateProductsAndDigitalStock($data['items']);

            $saleOrder = $this->saleOrderRepository->createSaleOrder([
                'order_number' => $data['order_number'],
                'source' => SaleOrder::MANASTORE,
                'total_price' => 0,
                'status' => Status::PENDING->value,
            ]);

            $totalPrice = 0;

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

                $this->allocateDigitalProducts($item, $product, $quantity);

                $totalPrice += $subtotal;
            }

            $saleOrder->update([
                'status' => Status::COMPLETED->value,
                'total_price' => $totalPrice,
            ]);

            DB::commit();

            return $saleOrder->load(['items.digitalProducts']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate products exist and have sufficient locked inventory.
     */
    private function validateProductsAndDigitalStock(array $items): void
    {
        foreach ($items as $itemData) {
            $product = $this->productRepository->getProductById($itemData['product_id']);

            if (! $product) {
                throw new \Exception("Product with ID {$itemData['product_id']} not found.");
            }

            $this->validateItemInventory($product, $itemData['quantity']);
        }
    }

    /**
     * Inventory validation with row-level locking.
     */
    private function validateItemInventory(Product $product, int $quantity): void
    {
        if ($product->digitalProducts->isEmpty()) {
            throw new \Exception("Product {$product->name} has no digital products assigned.");
        }

        $totalAvailable = 0;
        foreach ($product->digitalProducts as $digitalProduct) {
            $totalAvailable += $this->digitalStockRepository->getDigitalProductQuantity($digitalProduct->id);
        }

        if ($totalAvailable < $quantity) {
            throw new \Exception(
                "Insufficient inventory for product {$product->name}. "
                ."Requested: {$quantity}, Available: {$totalAvailable}"
            );
        }
    }

    /**
     * Allocate and deduct locked inventory.
     */
    private function allocateDigitalProducts(SaleOrderItem $item, Product $product, int $quantity): void
    {
        $query = $product->digitalProducts();

        $digitalProducts = $product->fulfillment_mode === FulfillmentMode::PRICE->value
            ? $query->orderBy('cost_price', 'asc')->get()
            : $query->orderByPivot('priority', 'asc')->get();

        if ($digitalProducts->isEmpty()) {
            throw new \Exception("Product {$product->name} has no digital products assigned.");
        }

        $remaining = $quantity;

        foreach ($digitalProducts as $digitalProduct) {
            if ($remaining <= 0) {
                break;
            }

            $available = $this->digitalStockRepository->getDigitalProductQuantity($digitalProduct->id);
            $deduct = min($remaining, $available);

            if ($deduct <= 0) {
                continue;
            }

            $item->digitalProducts()->create([
                'digital_product_id' => $digitalProduct->id,
                'quantity_deducted' => $deduct,
            ]);

            // Deduct from purchase_order_items using repository
            $this->digitalStockRepository->deductDigitalProductQuantity($digitalProduct->id, $deduct);

            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new \Exception(
                "Could not fully allocate {$quantity} units for product {$product->name}. "
                ."Remaining: {$remaining}"
            );
        }
    }
}
