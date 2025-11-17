<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);
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

    public function test_admin_can_import_vouchers_with_xlsx_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create();

        $file = UploadedFile::fake()->create('vouchers.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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

    public function test_admin_can_import_vouchers_with_xls_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create();

        $file = UploadedFile::fake()->create('vouchers.xls', 100, 'application/vnd.ms-excel');

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

    public function test_admin_can_import_vouchers_with_zip_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create();

        $file = UploadedFile::fake()->create('vouchers.zip', 100, 'application/zip');

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

        // Verify vouchers were created
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-001',
            'purchase_order_id' => $purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-002',
            'purchase_order_id' => $purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-003',
            'purchase_order_id' => $purchaseOrder->id,
        ]);
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
}
