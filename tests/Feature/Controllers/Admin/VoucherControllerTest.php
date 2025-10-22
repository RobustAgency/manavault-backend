<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $user;

    public function setUp(): void
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

        $file = UploadedFile::fake()->createWithContent('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
                'data' => null,
            ]);
    }

    public function test_admin_can_import_vouchers_with_xlsx_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
                'data' => null,
            ]);
    }

    public function test_admin_can_import_vouchers_with_xls_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.xls', 100, 'application/vnd.ms-excel');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
                'data' => null,
            ]);
    }

    public function test_admin_can_import_vouchers_with_zip_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.zip', 100, 'application/zip');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
                'data' => null,
            ]);
    }

    public function test_import_vouchers_requires_file(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->postJson('/api/admin/vouchers/import', [
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_vouchers_requires_purchase_order_id(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_import_vouchers_validates_file_type(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/admin/vouchers/import', [
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

        $response = $this->postJson('/api/admin/vouchers/import', [
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

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_unauthenticated_user_cannot_import_vouchers(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_import_vouchers(): void
    {
        $this->actingAs($this->user);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $file = UploadedFile::fake()->create('vouchers.csv', 100, 'text/csv');

        $response = $this->postJson('/api/admin/vouchers/import', [
            'file' => $file,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $response->assertStatus(403);
    }
}
