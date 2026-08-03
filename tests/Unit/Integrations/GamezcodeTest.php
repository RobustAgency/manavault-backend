<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Integrations\Gamezcode;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Enums\PurchaseOrderItemStatus;
use App\Services\Voucher\VoucherCipherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GamezcodeTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private DigitalProduct $product;

    private PurchaseOrder $purchaseOrder;

    private PurchaseOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gamezcode.base_url' => 'https://sandbox.kalixo.io/v2',
            'services.gamezcode.api_key' => 'kal_test_key',
        ]);

        $this->supplier = Supplier::factory()->create(['slug' => 'gamezcode']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create([
            'sku' => '10020000213575',
            'face_value' => 100.00,
            'currency' => 'gbp',
        ]);
        $this->purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'GBP']);
        $this->item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 95.00,
            'subtotal' => 95.00,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);
    }

    private function placeOrderResponse(string $orderId = 'ORDER-UUID-1', string $status = 'processing'): array
    {
        return [
            '*/orders/order_item_id_*' => Http::response([], 404),
            '*/orders' => Http::response([
                'orderId' => $orderId,
                'externalOrderCode' => 'order_item_id_'.$this->item->id,
                'status' => $status,
            ], 200),
        ];
    }

    private function getOrderResponse(string $status, array $codes = [], int $quantity = 1): array
    {
        $body = [
            'orderId' => 'ORDER-UUID-1',
            'externalOrderCode' => 'order_item_id_'.$this->item->id,
            'status' => $status,
        ];

        if (! empty($codes) || $status !== 'processing') {
            $body['products'] = [
                [
                    'productCode' => '10020000213575',
                    'price' => 10000,
                    'quantity' => $quantity,
                    'codes' => array_map(fn (string $code) => ['code' => $code], $codes),
                ],
            ];
        }

        return ['*/orders/*' => Http::response($body, 200)];
    }

    private function productsResponse(array $products): array
    {
        return [
            '*/catalog/products*' => Http::response([
                'count' => count($products),
                'skip' => 0,
                'take' => 100,
                'products' => $products,
            ], 200),
        ];
    }

    private function sampleProduct(array $overrides = []): array
    {
        return array_merge([
            'productCode' => '10020000213575',
            'name' => 'Sony PlayStation®Network Wallet Top-Up £100',
            'image' => 'https://cdn.kalixo.io/static-images/GB-EN-10020000213575.png',
            'price' => 10000,
            'currencyCode' => 'GBP',
            'buyingPrice' => 9500,
            'buyingCurrencyCode' => 'GBP',
            'brand' => 'PlayStation',
            'countryCode' => 'GB',
            'status' => 'active',
            'type' => 'digital',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // placeOrder
    // -------------------------------------------------------------------------

    public function test_place_order_makes_api_call_and_sets_transaction_id(): void
    {
        Http::fake($this->placeOrderResponse('ORDER-UUID-1'));

        app(Gamezcode::class)->placeOrder($this->item);

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/orders'));
        $this->assertEquals('ORDER-UUID-1', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_sets_item_status_to_processing(): void
    {
        Http::fake($this->placeOrderResponse());

        app(Gamezcode::class)->placeOrder($this->item);

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_sends_expected_payload(): void
    {
        Http::fake($this->placeOrderResponse());

        app(Gamezcode::class)->placeOrder($this->item);

        Http::assertSent(function ($r) {
            if ($r->method() !== 'POST') {
                return false;
            }

            $body = $r->data();

            return $body['externalOrderCode'] === 'order_item_id_'.$this->item->id
                && $body['currency'] === 'GBP'
                && $body['orderProducts'][0]['productCode'] === '10020000213575'
                && $body['orderProducts'][0]['quantity'] === 1
                && ! array_key_exists('price', $body['orderProducts'][0])
                && ! array_key_exists('sku', $body['orderProducts'][0]);
        });
    }

    public function test_place_order_skips_when_transaction_id_already_exists(): void
    {
        Http::fake();

        $this->item->update(['transaction_id' => 'existing-order']);

        app(Gamezcode::class)->placeOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertEquals('existing-order', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_skips_and_syncs_state_when_order_already_exists_upstream(): void
    {
        Http::fake([
            '*/orders/order_item_id_*' => Http::response([
                'orderId' => 'EXISTING-ORDER-UUID',
                'externalOrderCode' => 'order_item_id_'.$this->item->id,
                'status' => 'processing',
            ], 200),
        ]);

        app(Gamezcode::class)->placeOrder($this->item->fresh());

        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
        $this->assertEquals('EXISTING-ORDER-UUID', $this->item->fresh()->transaction_id);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_does_not_store_vouchers(): void
    {
        Http::fake($this->placeOrderResponse());

        app(Gamezcode::class)->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 0);
    }

    // -------------------------------------------------------------------------
    // updateOrder
    // -------------------------------------------------------------------------

    public function test_update_order_skips_when_no_transaction_id(): void
    {
        Http::fake();

        app(Gamezcode::class)->updateOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_update_order_does_nothing_when_processing(): void
    {
        $this->item->update(['transaction_id' => 'ORDER-UUID-1', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('processing'));

        app(Gamezcode::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_does_nothing_when_partially_completed(): void
    {
        $this->item->update([
            'quantity' => 5,
            'transaction_id' => 'ORDER-UUID-1',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        Http::fake($this->getOrderResponse('partially_completed', ['AAAAA', 'BBBBB', 'CCCCC'], 5));

        app(Gamezcode::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_stores_vouchers_and_marks_item_fulfilled_when_completed(): void
    {
        $this->item->update(['transaction_id' => 'ORDER-UUID-1', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('completed', ['ABCDE-FGHIJ-KLMNO-PQRST-UVWXY']));

        app(Gamezcode::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'status' => 'available',
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_stores_encrypted_codes(): void
    {
        $this->item->update(['transaction_id' => 'ORDER-UUID-1', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('completed', ['ABCDE-FGHIJ-KLMNO-PQRST-UVWXY']));

        app(Gamezcode::class)->updateOrder($this->item->fresh());

        $voucher = Voucher::where('purchase_order_item_id', $this->item->id)->first();

        $this->assertNotEquals('ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', $voucher->code);
        $this->assertEquals(
            'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            app(VoucherCipherService::class)->decryptCode($voucher->code)
        );
    }

    // -------------------------------------------------------------------------
    // syncProducts
    // -------------------------------------------------------------------------

    public function test_sync_products_stores_catalog_converting_minor_units(): void
    {
        Http::fake($this->productsResponse([$this->sampleProduct()]));

        app(Gamezcode::class)->syncProducts();

        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'sku' => '10020000213575',
            'name' => 'Sony PlayStation®Network Wallet Top-Up £100',
            'brand' => 'PlayStation',
            'face_value' => 100.00,   // 10000 / 100
            'cost_price' => 95.00,    // 9500 / 100
            'currency' => 'gbp',
            'region' => 'GB',
            'is_active' => true,
        ]);
    }

    public function test_sync_products_deactivates_stale_products(): void
    {
        $stale = DigitalProduct::factory()->forSupplier($this->supplier)->create([
            'sku' => 'STALE-SKU',
            'is_active' => true,
        ]);

        Http::fake($this->productsResponse([$this->sampleProduct()]));

        app(Gamezcode::class)->syncProducts();

        $this->assertFalse($stale->fresh()->is_active);
    }
}
