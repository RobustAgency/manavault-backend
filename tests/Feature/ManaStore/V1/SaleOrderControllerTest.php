<?php

namespace Tests\Feature\ManaStore\V1;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Foundation\Testing\WithFaker;
use App\Services\Voucher\VoucherCipherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private string $apiKey;

    private VoucherCipherService $voucherCipherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = config('services.manastore.api_key');
        $this->voucherCipherService = app(VoucherCipherService::class);
    }

    /**
     * Helper to make authenticated requests to ManaStore API.
     */
    private function manaStoreRequest(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
        ])->{$method}("/api{$uri}", $data);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $response = $this->getJson("/api/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $response = $this->withHeaders([
            'X-API-KEY' => 'invalid-api-key',
            'Accept' => 'application/json',
        ])->getJson("/api/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_can_create_sale_order(): void
    {
        // Create product with digital product and available vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 50.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(5)
            ->create();

        Voucher::factory()->count(5)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $response = $this->manaStoreRequest('postJson', '/v1/sale-orders', [
            'order_number' => 'MS-2026-000001',
            'source' => 'manastore',
            'currency' => 'USD',
            'subtotal' => 10000,
            'conversion_fees' => 0,
            'total' => 10000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'price' => 5000,
                    'purchase_price' => 4000,
                    'conversion_fee' => 0,
                    'total_price' => 10000,
                    'discount_amount' => 0,
                    'currency' => 'USD',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'source',
                    'total_price',
                    'status',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Sale order created successfully.',
            ]);

        $this->assertDatabaseHas('sale_orders', [
            'order_number' => 'MS-2026-000001',
        ]);
    }

    public function test_create_sale_order_validates_required_fields(): void
    {
        $response = $this->manaStoreRequest('postJson', '/v1/sale-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_number', 'currency', 'subtotal', 'conversion_fees', 'total', 'items']);
    }

    public function test_create_sale_order_validates_product_exists(): void
    {
        $response = $this->manaStoreRequest('postJson', '/v1/sale-orders', [
            'order_number' => 'MS-2026-000002',
            'currency' => 'USD',
            'subtotal' => 1000,
            'conversion_fees' => 0,
            'total' => 1000,
            'items' => [
                [
                    'product_id' => 99999,
                    'product_name' => 'Ghost Product',
                    'quantity' => 1,
                    'price' => 1000,
                    'purchase_price' => 800,
                    'conversion_fee' => 0,
                    'total_price' => 1000,
                    'discount_amount' => 0,
                    'currency' => 'USD',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_can_get_voucher_codes_for_sale_order(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $saleOrder = SaleOrder::factory()->create();
        $saleOrderItem = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->create();

        // Create voucher with plain text code (simulating legacy data)
        $voucher = Voucher::factory()->create([
            'code' => 'PLAIN-CODE-123',
            'serial_number' => 'SN-123456',
            'pin_code' => '1234',
        ]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem)
            ->create(['voucher_id' => $voucher->id]);

        $response = $this->manaStoreRequest('getJson', "/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    '*' => [
                        'title',
                        'codes' => [
                            '*' => [
                                'code',
                                'serial_number',
                                'pin_code',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Test Product', $data[0]['title']);
        $this->assertEquals('PLAIN-CODE-123', $data[0]['codes'][0]['code']);
        $this->assertEquals('SN-123456', $data[0]['codes'][0]['serial_number']);
        $this->assertEquals('1234', $data[0]['codes'][0]['pin_code']);
    }

    public function test_voucher_codes_are_decrypted_before_returning(): void
    {
        $product = Product::factory()->create(['name' => 'Encrypted Product']);
        $saleOrder = SaleOrder::factory()->create();
        $saleOrderItem = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->create();

        // Create voucher with encrypted code
        $plainCode = 'SECRET-VOUCHER-CODE';
        $encryptedCode = $this->voucherCipherService->encryptCode($plainCode);

        $voucher = Voucher::factory()->create([
            'code' => $encryptedCode,
            'serial_number' => 'SN-ENCRYPTED',
            'pin_code' => '5678',
        ]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem)
            ->create(['voucher_id' => $voucher->id]);

        $response = $this->manaStoreRequest('getJson', "/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Encrypted Product', $data[0]['title']);

        // Verify the code is decrypted (not the encrypted version)
        $this->assertEquals($plainCode, $data[0]['codes'][0]['code']);
        $this->assertNotEquals($encryptedCode, $data[0]['codes'][0]['code']);
    }

    public function test_get_voucher_codes_returns_empty_when_no_vouchers(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $response = $this->manaStoreRequest('getJson', "/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
                'data' => [],
            ]);
    }

    public function test_get_voucher_codes_groups_by_product(): void
    {
        $product1 = Product::factory()->create(['name' => 'Product A']);
        $product2 = Product::factory()->create(['name' => 'Product B']);
        $saleOrder = SaleOrder::factory()->create();

        $saleOrderItem1 = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product1)
            ->create();

        $saleOrderItem2 = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product2)
            ->create();

        // Create vouchers for product 1
        $voucher1 = Voucher::factory()->create(['code' => 'CODE-A1']);
        $voucher2 = Voucher::factory()->create(['code' => 'CODE-A2']);

        // Create voucher for product 2
        $voucher3 = Voucher::factory()->create(['code' => 'CODE-B1']);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem1)
            ->create(['voucher_id' => $voucher1->id]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem1)
            ->create(['voucher_id' => $voucher2->id]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem2)
            ->create(['voucher_id' => $voucher3->id]);

        $response = $this->manaStoreRequest('getJson', "/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Find product groups by title
        $productAData = collect($data)->firstWhere('title', 'Product A');
        $productBData = collect($data)->firstWhere('title', 'Product B');

        $this->assertNotNull($productAData);
        $this->assertNotNull($productBData);
        $this->assertCount(2, $productAData['codes']);
        $this->assertCount(1, $productBData['codes']);
    }

    public function test_get_voucher_codes_returns_404_for_nonexistent_sale_order(): void
    {
        $response = $this->manaStoreRequest('getJson', '/v1/sale-orders/99999/vouchers');

        $response->assertStatus(404);
    }

    public function test_mixed_encrypted_and_plain_voucher_codes(): void
    {
        $product = Product::factory()->create(['name' => 'Mixed Product']);
        $saleOrder = SaleOrder::factory()->create();
        $saleOrderItem = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->create();

        // Create one plain text voucher (legacy)
        $plainVoucher = Voucher::factory()->create([
            'code' => 'PLAIN-LEGACY-CODE',
            'serial_number' => 'SN-PLAIN',
            'pin_code' => '1111',
        ]);

        // Create one encrypted voucher (new format)
        $secretCode = 'ENCRYPTED-SECRET-CODE';
        $encryptedVoucher = Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($secretCode),
            'serial_number' => 'SN-ENCRYPTED',
            'pin_code' => '2222',
        ]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem)
            ->create(['voucher_id' => $plainVoucher->id]);

        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($saleOrderItem)
            ->create(['voucher_id' => $encryptedVoucher->id]);

        $response = $this->manaStoreRequest('getJson', "/v1/sale-orders/{$saleOrder->id}/vouchers");

        $response->assertStatus(200);

        $data = $response->json('data');
        $codes = collect($data[0]['codes']);

        // Both plain and encrypted codes should be returned as plain text
        $codeValues = $codes->pluck('code')->toArray();
        $this->assertContains('PLAIN-LEGACY-CODE', $codeValues);
        $this->assertContains($secretCode, $codeValues);
    }
}
