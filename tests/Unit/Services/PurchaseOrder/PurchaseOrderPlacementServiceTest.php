<?php

namespace Tests\Unit\Services\PurchaseOrder;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Events\NewVouchersAvailable;
use Illuminate\Support\Facades\Http;
use App\Integrations\EzCards\EzCards;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

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

    // ── EZCards

    public function test_ezcards_places_order_via_orders_endpoint(): void
    {
        Http::fake([
            '*/v2/orders*' => Http::response([
                'data' => ['transactionId' => 'EZ-TX-001', 'status' => 'pending'],
            ], 200),
        ]);

        [, $item, $po] = $this->makeEzCardsFixtures();

        $result = app(EzCards::class)->placeOrder([$item], 'PO-EZ-001', 'usd', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/orders'));
        $this->assertEquals('EZ-TX-001', $result['transactionId']);
    }

    public function test_ezcards_sends_correct_payload(): void
    {
        Http::fake([
            '*/v2/orders*' => Http::response(['data' => ['transactionId' => 'EZ-TX-002']], 200),
        ]);

        [, $item, $po] = $this->makeEzCardsFixtures('EZ-SKU-001', 3);

        app(EzCards::class)->placeOrder([$item], 'PO-EZ-002', 'USD', $po);

        Http::assertSent(function ($r) {
            $body = $r->data();

            return str_contains($r->url(), '/v2/orders')
                && $body['clientOrderNumber'] === 'PO-EZ-002'
                && $body['products'][0]['sku'] === 'EZ-SKU-001'
                && $body['products'][0]['quantity'] === 3
                && $body['payWithCurrency'] === 'USD';
        });
    }

    public function test_ezcards_returns_empty_array_when_data_key_missing(): void
    {
        Http::fake([
            '*/v2/orders*' => Http::response(['success' => true], 200),
        ]);

        [, $item, $po] = $this->makeEzCardsFixtures();

        $result = app(EzCards::class)->placeOrder([$item], 'PO-EZ-003', 'usd', $po);

        $this->assertSame([], $result);
    }

    public function test_ezcards_throws_on_api_failure(): void
    {
        Http::fake([
            '*/v2/orders*' => Http::response('Internal Server Error', 500),
        ]);

        [, $item, $po] = $this->makeEzCardsFixtures();

        $this->expectException(\Exception::class);

        app(EzCards::class)->placeOrder([$item], 'PO-EZ-004', 'usd', $po);
    }

    public function test_ezcards_fetches_voucher_codes(): void
    {
        Http::fake([
            '*/v2/orders/*/codes*' => Http::response([
                'data' => [['code' => 'VOUCHER-001', 'serial' => 'SN-001']],
            ], 200),
        ]);

        $po = PurchaseOrder::factory()->create();

        $result = app(EzCards::class)->fetchVouchers('12345', $po);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/orders/12345/codes'));
        $this->assertCount(1, $result);
        $this->assertEquals('VOUCHER-001', $result[0]['code']);
    }

    public function test_legacy_service_throws_for_ez_cards_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards', 'type' => 'external']);
        $po = PurchaseOrder::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown external supplier: ez_cards');

        $this->service->placeOrder($supplier, [], 'PO-007', 'usd', $po);
    }

    // ── Unknown supplier ──────────────────────────────────────────────────────

    public function test_throws_for_unknown_supplier_slug(): void
    {
        $supplier = Supplier::factory()->create(['slug' => 'acme-vouchers', 'type' => 'external']);
        $po = PurchaseOrder::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown external supplier: acme-vouchers');

        $this->service->placeOrder($supplier, [], 'PO-008', 'usd', $po);
    }

    // ── Helpers

    /**
     * @return array{0: Supplier, 1: PurchaseOrderItem, 2: PurchaseOrder}
     */
    private function makeEzCardsFixtures(string $sku = 'EZ-001', int $quantity = 1): array
    {
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards', 'type' => 'external']);
        $product = DigitalProduct::factory()->create(['sku' => $sku, 'supplier_id' => $supplier->id]);
        $po = PurchaseOrder::factory()->create(['order_number' => 'PO-EZ', 'currency' => 'usd']);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => 5.00,
            'subtotal' => 5.00 * $quantity,
        ]);

        return [$supplier, $item, $po];
    }
}
