<?php

namespace Tests\Unit\Services\PurchaseOrder;

use App\Events\NewVouchersAvailable;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseOrderPlacementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderPlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchaseOrderPlacementService::class);
        Event::fake([NewVouchersAvailable::class]);
    }

    // ── Gift2Games (USD / EUR / GBP) ──────────────────────────────────────────

    public function test_routes_gift2games_slug_to_create_order_endpoint(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => 999]], 200),
            '*/create_order'  => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-1', 'serialNumber' => 'SN-1']], 200),
        ]);

        [$supplier, $item, $po] = $this->makeG2GFixtures('gift2games');

        $this->service->placeOrder($supplier, [$item], 'PO-001', 'usd', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    public function test_routes_gift2games_eur_wallet(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => 999]], 200),
            '*/create_order'  => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-2', 'serialNumber' => 'SN-2']], 200),
        ]);

        [$supplier, $item, $po] = $this->makeG2GFixtures('gift-2-games-eur');

        $this->service->placeOrder($supplier, [$item], 'PO-002', 'eur', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    public function test_routes_gift2games_gbp_wallet(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => 999]], 200),
            '*/create_order'  => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-3', 'serialNumber' => 'SN-3']], 200),
        ]);

        [$supplier, $item, $po] = $this->makeG2GFixtures('gift-2-games-gbp');

        $this->service->placeOrder($supplier, [$item], 'PO-003', 'gbp', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    // ── Giftery ───────────────────────────────────────────────────────────────

    public function test_routes_giftery_to_reserve_and_confirm_endpoints(): void
    {
        Http::fake([
            '*/auth*'              => Http::response(['data' => ['accessToken' => 'tok', 'refreshToken' => 'ref', 'expiresIn' => 3600]], 200),
            '*/accounts'           => Http::response(['statusCode' => 0, 'data' => [['default' => true, 'available' => 999, 'balance' => 999]]], 200),
            '*products/items/*'    => Http::response(['statusCode' => 0, 'data' => ['inStock' => 10]], 200),
            '*/operations/reserve' => Http::response(['statusCode' => 0, 'data' => ['transactionUUID' => 'uuid-abc', 'status' => 'reserved']], 200),
            '*/operations/*'       => Http::response(['statusCode' => 0, 'data' => ['status' => 'confirmed', 'codes' => [['code' => 'GIFT-CODE', 'serial' => 'SN-G']]]], 200),
            '*'                    => Http::response(['statusCode' => 0, 'data' => []], 200),
        ]);

        $supplier = Supplier::factory()->create(['slug' => 'giftery-api', 'type' => 'external']);
        $product  = DigitalProduct::factory()->create([
            'sku'         => '1001',
            'supplier_id' => $supplier->id,
            'metadata'    => ['item_id' => 'ITEM-1', 'product_id' => 'PROD-1'],
        ]);
        $po   = PurchaseOrder::factory()->create(['order_number' => 'PO-004', 'currency' => 'usd']);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id'  => $po->id,
            'supplier_id'        => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity'           => 1,
            'unit_cost'          => 10.00,
            'subtotal'           => 10.00,
        ]);

        $this->service->placeOrder($supplier, [$item], 'PO-004', 'usd', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/operations/reserve'));
    }

    // ── Tikkery ───────────────────────────────────────────────────────────────

    public function test_routes_tikkery_to_orders_create_endpoint(): void
    {
        Http::fake([
            '*/oauth/token'   => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 3600], 200),
            '*/balance*'      => Http::response(['balance' => 999.00], 200),
            '*/products/stock*' => Http::response(['stock' => [['sku' => 'TIK-001', 'stock' => 99]]], 200),
            '*/orders/create' => Http::response(['transactionId' => 'ti-789', 'isCompleted' => false, 'codes' => []], 200),
            '*'               => Http::response([], 200),
        ]);

        $supplier = Supplier::factory()->create(['slug' => 'tikkery', 'type' => 'external']);
        $product  = DigitalProduct::factory()->create(['sku' => 'TIK-001', 'supplier_id' => $supplier->id]);
        $po       = PurchaseOrder::factory()->create(['order_number' => 'PO-005', 'currency' => 'usd']);
        $item     = PurchaseOrderItem::factory()->create([
            'purchase_order_id'  => $po->id,
            'supplier_id'        => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity'           => 1,
            'unit_cost'          => 15.00,
            'subtotal'           => 15.00,
        ]);

        $result = $this->service->placeOrder($supplier, [$item], 'PO-005', 'usd', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/orders/create'));
        $this->assertArrayHasKey('transactionId', $result);
    }

    // ── Irewardify ────────────────────────────────────────────────────────────

    public function test_routes_irewardify_to_checkout_endpoint(): void
    {
        Http::fake([
            '*/customer/login'  => Http::response(['token' => 'ir-tok'], 200),
            '*/customer/wallet' => Http::response(['data' => ['walletOne' => 999.00]], 200),
            '*/checkout'        => Http::response(['data' => ['orderId' => 'ir-999']], 200),
            '*'                 => Http::response(['data' => []], 200),
        ]);

        $supplier = Supplier::factory()->create(['slug' => 'irewardify', 'type' => 'external']);
        $product  = DigitalProduct::factory()->create([
            'sku'         => 'IR-001',
            'supplier_id' => $supplier->id,
            'metadata'    => ['variant' => ['item_id' => 'ITEM-1', 'product_id' => 'PROD-1']],
        ]);
        $po   = PurchaseOrder::factory()->create(['order_number' => 'PO-006', 'currency' => 'usd']);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id'    => $po->id,
            'supplier_id'          => $supplier->id,
            'digital_product_id'   => $product->id,
            'digital_product_sku'  => 'IR-001',
            'quantity'             => 1,
            'unit_cost'            => 20.00,
            'subtotal'             => 20.00,
        ]);

        $this->service->placeOrder($supplier, [$item], 'PO-006', 'usd', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkout'));
    }

    // ── EZCards — not handled by this service ─────────────────────────────────

    public function test_throws_for_ez_cards_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards', 'type' => 'external']);
        $po       = PurchaseOrder::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown external supplier: ez_cards');

        $this->service->placeOrder($supplier, [], 'PO-007', 'usd', $po);
    }

    // ── Unknown supplier ──────────────────────────────────────────────────────

    public function test_throws_for_unknown_supplier_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'acme-vouchers', 'type' => 'external']);
        $po       = PurchaseOrder::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown external supplier: acme-vouchers');

        $this->service->placeOrder($supplier, [], 'PO-008', 'usd', $po);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{0: Supplier, 1: PurchaseOrderItem, 2: PurchaseOrder}
     */
    private function makeG2GFixtures(string $slug): array
    {
        $supplier = Supplier::factory()->create(['slug' => $slug, 'type' => 'external']);
        $product  = DigitalProduct::factory()->create(['sku' => '99001', 'cost_price' => 10.00, 'supplier_id' => $supplier->id]);
        $po       = PurchaseOrder::factory()->create(['order_number' => 'PO-G2G', 'currency' => 'usd']);
        $item     = PurchaseOrderItem::factory()->create([
            'purchase_order_id'  => $po->id,
            'supplier_id'        => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity'           => 1,
            'unit_cost'          => 10.00,
            'subtotal'           => 10.00,
        ]);

        return [$supplier, $item, $po];
    }
}
