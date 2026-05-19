<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlacePendingPurchaseOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    private const EZ_CARDS_ORDERS_URL = 'https://sandboxapi.ezcards.io/v2/orders';

    private function ezCardsSupplier(): Supplier
    {
        return Supplier::factory()->create([
            'name' => 'Ez Cards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);
    }

    private function pendingSupplierRow(Supplier $supplier, PurchaseOrder $purchaseOrder): PurchaseOrderSupplier
    {
        return PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);
    }

    private function orderItem(PurchaseOrder $purchaseOrder, Supplier $supplier, string $sku = 'AAU-QB-Q1J'): PurchaseOrderItem
    {
        $product = DigitalProduct::factory()->forSupplier($supplier)->create(['sku' => $sku]);

        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    private function placeOrderResponse(int $transactionId = 123456, string $orderNumber = 'PO-TEST-001'): array
    {
        return [
            'data' => [
                'transactionId' => $transactionId,
                'clientOrderNumber' => $orderNumber,
                'status' => 'PROCESSING',
                'products' => [
                    [
                        'sku' => 'AAU-QB-Q1J',
                        'quantity' => 2,
                        'status' => 'PROCESSING',
                    ],
                ],
            ],
        ];
    }

    public function test_does_nothing_when_no_pending_orders(): void
    {
        Http::fake();

        $this->artisan('purchase-order:place-pending')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_places_order_and_saves_transaction_id(): void
    {
        $supplier = $this->ezCardsSupplier();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'order_number' => 'PO-20240101-ABCDEF',
            'currency' => 'usd',
            'status' => 'processing',
        ]);
        $supplierRow = $this->pendingSupplierRow($supplier, $purchaseOrder);
        $this->orderItem($purchaseOrder, $supplier);

        Http::fake([
            self::EZ_CARDS_ORDERS_URL => Http::response($this->placeOrderResponse(123456, 'PO-20240101-ABCDEF'), 200),
        ]);

        $this->artisan('purchase-order:place-pending')->assertSuccessful();

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $supplierRow->id,
            'transaction_id' => '123456',
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        Http::assertSentCount(1);
    }

    public function test_skips_supplier_row_that_already_has_transaction_id(): void
    {
        $supplier = $this->ezCardsSupplier();
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'usd', 'status' => 'processing']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => '99999',
        ]);

        Http::fake();

        $this->artisan('purchase-order:place-pending')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_skips_supplier_without_integration(): void
    {
        $legacySupplier = Supplier::factory()->create([
            'slug' => 'giftery-api',
            'type' => 'external',
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'usd', 'status' => 'processing']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $legacySupplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        Http::fake();

        $this->artisan('purchase-order:place-pending')->assertSuccessful();

        Http::assertNothingSent();

        // Row stays untouched — still processing with no transaction_id
        $this->assertDatabaseHas('purchase_order_suppliers', [
            'supplier_id' => $legacySupplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);
    }

    public function test_marks_supplier_as_failed_on_api_error(): void
    {
        $supplier = $this->ezCardsSupplier();
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'usd', 'status' => 'processing']);
        $supplierRow = $this->pendingSupplierRow($supplier, $purchaseOrder);
        $this->orderItem($purchaseOrder, $supplier);

        Http::fake([
            self::EZ_CARDS_ORDERS_URL => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $this->artisan('purchase-order:place-pending')->assertFailed();

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $supplierRow->id,
            'status' => PurchaseOrderSupplierStatus::FAILED->value,
            'transaction_id' => null,
        ]);
    }

    public function test_places_multiple_pending_orders(): void
    {
        $supplier = $this->ezCardsSupplier();

        $purchaseOrder1 = PurchaseOrder::factory()->create(['order_number' => 'PO-001', 'currency' => 'usd', 'status' => 'processing']);
        $purchaseOrder2 = PurchaseOrder::factory()->create(['order_number' => 'PO-002', 'currency' => 'usd', 'status' => 'processing']);

        $supplierRow1 = $this->pendingSupplierRow($supplier, $purchaseOrder1);
        $supplierRow2 = $this->pendingSupplierRow($supplier, $purchaseOrder2);

        $this->orderItem($purchaseOrder1, $supplier, 'AAU-QB-Q1J');
        $this->orderItem($purchaseOrder2, $supplier, 'SMS-1B-I4A');

        Http::fake([
            self::EZ_CARDS_ORDERS_URL => Http::sequence()
                ->push($this->placeOrderResponse(111111, 'PO-001'), 200)
                ->push($this->placeOrderResponse(222222, 'PO-002'), 200),
        ]);

        $this->artisan('purchase-order:place-pending')->assertSuccessful();

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $supplierRow1->id,
            'transaction_id' => '111111',
        ]);

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $supplierRow2->id,
            'transaction_id' => '222222',
        ]);

        Http::assertSentCount(2);
    }

    public function test_continues_placing_remaining_orders_after_one_failure(): void
    {
        $supplier = $this->ezCardsSupplier();

        $purchaseOrder1 = PurchaseOrder::factory()->create(['order_number' => 'PO-FAIL', 'currency' => 'usd', 'status' => 'processing']);
        $purchaseOrder2 = PurchaseOrder::factory()->create(['order_number' => 'PO-OK', 'currency' => 'usd', 'status' => 'processing']);

        $failRow = $this->pendingSupplierRow($supplier, $purchaseOrder1);
        $successRow = $this->pendingSupplierRow($supplier, $purchaseOrder2);

        $this->orderItem($purchaseOrder1, $supplier, 'AAU-QB-Q1J');
        $this->orderItem($purchaseOrder2, $supplier, 'SMS-1B-I4A');

        Http::fake([
            self::EZ_CARDS_ORDERS_URL => Http::sequence()
                ->push(['message' => 'Bad Request'], 400)
                ->push($this->placeOrderResponse(555555, 'PO-OK'), 200),
        ]);

        $this->artisan('purchase-order:place-pending')->assertFailed();

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $failRow->id,
            'status' => PurchaseOrderSupplierStatus::FAILED->value,
        ]);

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id' => $successRow->id,
            'transaction_id' => '555555',
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
    }
}
