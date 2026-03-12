<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use App\Enums\Product\FulfillmentMode;
use App\Repositories\ProductRepository;
use App\Repositories\SaleOrderRepository;

class SaleOrderService
{
    public function __construct(
        private ProductRepository $productRepository,
        private SaleOrderRepository $saleOrderRepository,
        private VoucherAllocationService $voucherAllocationService
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

            // Dispatch event to sync stock for all products sharing the affected digital products
            event(new SaleOrderCompleted($saleOrder));

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
     * Checks available unallocated vouchers for the product.
     */
    private function validateItemInventory(Product $product, int $quantity): void
    {
        if ($product->digitalProducts->isEmpty()) {
            throw new \Exception("Product {$product->name} has no digital products assigned.");
        }

        $totalAvailable = 0;
        foreach ($product->digitalProducts as $digitalProduct) {
            $totalAvailable += $this->voucherAllocationService->getAvailableQuantity($digitalProduct->id);
        }

        if ($totalAvailable < $quantity) {
            throw new \Exception(
                "Insufficient inventory for product {$product->name}. "
                ."Requested: {$quantity}, Available: {$totalAvailable}"
            );
        }
    }

    /**
     * Allocate vouchers to digital products in a sale order item.
     *
     * Does NOT modify purchase orders (maintains immutability).
     * Each allocated voucher is stored as a record in sale_order_item_digital_products.
     */
    private function allocateDigitalProducts(SaleOrderItem $item, Product $product, int $quantity): void
    {
        $query = $product->digitalProducts();

        /** @var \Illuminate\Database\Eloquent\Collection<int, DigitalProduct> $digitalProducts */
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

            try {
                // Get available vouchers without modifying purchase orders
                $vouchers = $this->voucherAllocationService
                    ->getAvailableVouchersForDigitalProduct($digitalProduct->id, $remaining);

                // Create allocation records (one per voucher)
                foreach ($vouchers as $voucher) {
                    $this->voucherAllocationService->allocateVoucher(
                        $item->id,
                        $digitalProduct,
                        $voucher
                    );

                    $remaining--;
                }
            } catch (\Exception $e) {
                // Insufficient vouchers from this digital product, try next
                if (str_contains($e->getMessage(), 'Insufficient vouchers')) {
                    continue;
                }
                throw $e;
            }
        }

        if ($remaining > 0) {
            throw new \Exception(
                "Could not fully allocate {$quantity} units for product {$product->name}. "
                ."Remaining: {$remaining}"
            );
        }
    }
}
