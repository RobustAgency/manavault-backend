<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\UploadedFile;
use App\Services\VoucherCipherService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    private User $user;

    private VoucherCipherService $voucherCipherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);
        $this->voucherCipherService = app(VoucherCipherService::class);
        Storage::fake('local');
    }

    public function test_admin_can_import_vouchers_with_csv_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create();

        // Create a real CSV file with 3 voucher codes to match purchase order quantity
        $csvContent = "code\nVCH-001\nVCH-002\nVCH-003";
        $file = UploadedFile::fake()->createWithContent('vouchers.csv', $csvContent);

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
            ]);
    }

    public function test_admin_can_import_vouchers_with_voucher_codes_array(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create();

        $response = $this->postJson('/api/admin/vouchers/store', [
            'voucher_codes' => [
                'CODE-001',
                'CODE-002',
                'CODE-003',
            ],
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
            ]);

        // Verify vouchers were created and are encrypted
        $vouchers = Voucher::where('purchase_order_id', $purchaseOrder->id)->get();
        $this->assertCount(3, $vouchers);

        $decryptedCodes = $vouchers->map(function ($voucher) {
            return $this->voucherCipherService->decryptCode($voucher->code);
        })->toArray();

        $this->assertContains('CODE-001', $decryptedCodes);
        $this->assertContains('CODE-002', $decryptedCodes);
        $this->assertContains('CODE-003', $decryptedCodes);
    }

    public function test_import_vouchers_requires_file_or_voucher_codes(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $response = $this->postJson('/api/admin/vouchers/store', [
            'purchase_order_id' => $purchaseOrder->id,
            // Neither file nor voucher_codes provided
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'voucher_codes']);
    }

    public function test_import_vouchers_requires_purchase_order_id(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_import_vouchers_with_voucher_codes_requires_array(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $response = $this->postJson('/api/admin/vouchers/store', [
            'voucher_codes' => 'not-an-array',
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['voucher_codes']);
    }

    public function test_import_vouchers_with_voucher_codes_requires_at_least_one_code(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $response = $this->postJson('/api/admin/vouchers/store', [
            'voucher_codes' => [],
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['voucher_codes']);
    }

    public function test_import_vouchers_validates_file_type(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $file = UploadedFile::fake()->create('vouchers.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_vouchers_validates_purchase_order_exists(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => 99999, // Non-existent ID
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_import_vouchers_validates_purchase_order_id_is_integer(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_unauthenticated_user_cannot_import_vouchers(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_import_vouchers(): void
    {
        $this->actingAs($this->user);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create();

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/store', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_list_vouchers(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();

        // Create vouchers with encrypted codes
        $plainCode1 = 'LIST-VCH-001';
        $plainCode2 = 'LIST-VCH-002';
        $plainCode3 = 'LIST-VCH-003';

        Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($plainCode1),
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($plainCode2),
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($plainCode3),
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response = $this->getJson('/api/admin/vouchers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'purchase_order_id',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'per_page',
                    'total',
                    'last_page',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers retrieved successfully.',
            ]);
    }

    public function test_admin_can_list_vouchers_filtered_by_purchase_order(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder1 = PurchaseOrder::factory()->create();
        $purchaseOrder2 = PurchaseOrder::factory()->create();

        Voucher::factory()->count(3)->create(['purchase_order_id' => $purchaseOrder1->id]);
        Voucher::factory()->count(2)->create(['purchase_order_id' => $purchaseOrder2->id]);

        $response = $this->getJson("/api/admin/vouchers?purchase_order_id={$purchaseOrder1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3);
    }

    public function test_admin_can_show_voucher_with_encrypted_code(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $plainCode = 'SHOW-VCH-ENCRYPTED-123';

        $voucher = Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($plainCode),
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'available',
        ]);

        $response = $this->getJson("/api/admin/vouchers/{$voucher->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher retrieved successfully.',
                'data' => [
                    'id' => $voucher->id,
                    'code' => $plainCode, // Should be decrypted
                ],
            ]);
    }

    public function test_admin_can_show_voucher_with_legacy_plain_text_code(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $plainCode = 'SHOW-VCH-LEGACY-456';

        // Create voucher with plain text code (legacy)
        $voucher = Voucher::factory()->create([
            'code' => $plainCode,
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'available',
        ]);

        $response = $this->getJson("/api/admin/vouchers/{$voucher->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher retrieved successfully.',
                'data' => [
                    'id' => $voucher->id,
                    'code' => $plainCode, // Should return as-is
                ],
            ]);
    }

    public function test_show_voucher_returns_404_for_nonexistent_voucher(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/vouchers/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_list_vouchers(): void
    {
        $response = $this->getJson('/api/admin/vouchers');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_show_voucher(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $voucher = Voucher::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $response = $this->getJson("/api/admin/vouchers/{$voucher->id}");

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_list_vouchers(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/admin/vouchers');

        $response->assertStatus(403);
    }

    public function test_non_admin_user_cannot_show_voucher(): void
    {
        $this->actingAs($this->user);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $voucher = Voucher::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $response = $this->getJson("/api/admin/vouchers/{$voucher->id}");

        $response->assertStatus(403);
    }
}
