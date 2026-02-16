<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_admin_show_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/digital-products/999999');

        $response->assertStatus(404);
    }

    public function test_admin_create_single_digital_product(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'New Digital Product',
                    'sku' => 'SKU-12345',
                    'brand' => 'Test Brand',
                    'description' => 'Product description',
                    'tags' => ['software', 'productivity'],
                    'currency' => 'usd',
                    'region' => 'US',
                    'cost_price' => 149.99,
                    'metadata' => ['external_id' => 'ext-123'],
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    '*' => [
                        'id',
                        'supplier_id',
                        'name',
                        'sku',
                        'brand',
                        'description',
                        'tags',
                        'region',
                        'cost_price',
                        'currency',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital products created successfully.',
            ]);

        $this->assertDatabaseHas('digital_products', [
            'name' => 'New Digital Product',
            'sku' => 'SKU-12345',
            'brand' => 'Test Brand',
            'cost_price' => 149.99,
            'region' => 'US',
            'tags' => json_encode(['software', 'productivity']),
        ]);
    }

    public function test_admin_create_multiple_digital_products(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 1',
                    'sku' => 'SKU-001',
                    'brand' => 'Brand A',
                    'cost_price' => 10.00,
                    'currency' => 'usd',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 2',
                    'sku' => 'SKU-002',
                    'brand' => 'Brand B',
                    'cost_price' => 20.00,
                    'currency' => 'usd',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 3',
                    'sku' => 'SKU-003',
                    'brand' => 'Brand C',
                    'cost_price' => 30.00,
                    'currency' => 'eur',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201);
        $this->assertCount(3, $response->json('data'));
        $this->assertDatabaseCount('digital_products', 3);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 1', 'currency' => 'usd']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 2', 'currency' => 'usd']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 3', 'currency' => 'eur']);
    }

    public function test_admin_create_digital_product_validation_fails(): void
    {
        $this->actingAs($this->admin);

        $data = [
            'products' => [
                [
                    // Missing required fields
                    'name' => 'Test Product',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'products.0.supplier_id',
                'products.0.sku',
                'products.0.cost_price',
            ]);
    }

    public function test_admin_update_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Original Name',
            'cost_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'brand' => 'Updated Brand',
            'cost_price' => 199.99,
        ];

        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'brand',
                    'cost_price',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital product updated successfully.',
                'data' => [
                    'id' => $digitalProduct->id,
                    'name' => 'Updated Name',
                    'cost_price' => '199.99',
                ],
            ]);

        $this->assertDatabaseHas('digital_products', [
            'id' => $digitalProduct->id,
            'name' => 'Updated Name',
            'cost_price' => 199.99,
        ]);
    }

    public function test_admin_delete_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create();

        $response = $this->deleteJson("/api/digital-products/{$digitalProduct->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Digital product deleted successfully.',
            ]);

        $this->assertDatabaseMissing('digital_products', [
            'id' => $digitalProduct->id,
        ]);
    }

    public function test_admin_delete_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/digital-products/999999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_create_digital_product(): void
    {
        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Test Product',
                    'sku' => 'TEST-SKU',
                    'cost_price' => 99.99,
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(401);
    }

    public function test_admin_batch_import_digital_products_from_csv(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= "Gaming Card,SKU-GAMING-001,Nintendo,Nintendo Switch gift card,50.00,usd,US,gaming|cards,\n";
        $csvContent .= "Movie Voucher,SKU-MOVIE-001,Disney,Disney movie theater voucher,25.00,eur,EU,movies|entertainment,\n";
        $csvContent .= "Amazon Gift Card,SKU-AMAZON-001,Amazon,Amazon $100 gift card,100.00,eur,UK,shopping|cards,\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');

        $uploadedFile = new UploadedFile($tempFile, 'vouchers.csv', 'text/csv', null, true);

        $response = $this->postJson('/api/digital-products/batch-import', [
            'file' => $uploadedFile,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Digital products imported successfully.',
            ]);

        $this->assertDatabaseCount('digital_products', 3);
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $supplier->id,
            'name' => 'Gaming Card',
            'sku' => 'SKU-GAMING-001',
            'brand' => 'Nintendo',
        ]);
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $supplier->id,
            'name' => 'Movie Voucher',
            'sku' => 'SKU-MOVIE-001',
            'currency' => 'eur',
        ]);
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $supplier->id,
            'name' => 'Amazon Gift Card',
            'sku' => 'SKU-AMAZON-001',
            'currency' => 'eur',
        ]);

        $this->cleanupTempFile($tempFile);
    }

    public function test_admin_batch_import_with_nonexistent_supplier(): void
    {
        $this->actingAs($this->admin);

        $nonexistentSupplierId = 99999;

        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= "Gaming Card,SKU-GAMING-001,Nintendo,Nintendo Switch gift card,50.00,usd,US,gaming|cards,\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->postJson('/api/digital-products/batch-import', [
            'file' => $file,
            'supplier_id' => $nonexistentSupplierId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_admin_batch_import_with_invalid_currency(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= "Test Card,SKU-TEST,Test Brand,Test description,50.00,invalid_currency,US,test,\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->postJson('/api/digital-products/batch-import', [
            'file' => $file,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => true,
            ])
            ->assertJsonStructure([
                'error',
                'message',
            ]);

        $this->assertStringContainsString('currency', strtolower($response->json('message')));
    }

    public function test_admin_batch_import_requires_file(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $response = $this->postJson('/api/digital-products/batch-import', [
            'supplier_id' => $supplier->id,
            // Missing 'file'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_admin_batch_import_requires_supplier_id(): void
    {
        $this->actingAs($this->admin);

        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= "Test Card,SKU-TEST,Test Brand,Test description,50.00,usd,US,test,\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->postJson('/api/digital-products/batch-import', [
            'file' => $file,
            // Missing 'supplier_id'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_unauthenticated_user_cannot_batch_import(): void
    {
        $supplier = Supplier::factory()->create();

        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= "Test Card,SKU-TEST,Test Brand,Test description,50.00,usd,US,test,\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->postJson('/api/digital-products/batch-import', [
            'file' => $file,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Helper method to create a temporary file with specific content and extension
     */
    private function createTempFile(string $content, string $extension): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'voucher_test_').'.'.$extension;
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    /**
     * Helper method to clean up temporary files
     */
    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
