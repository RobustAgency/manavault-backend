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
                    'face_value' => 175.00,
                    'cost_price' => 149.99,
                    'selling_price' => 199.99,
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
                        'face_value',
                        'selling_price',
                        'selling_discount',
                        'cost_price_discount',
                        'profit_margin',
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
                    'face_value' => 12.00,
                    'cost_price' => 10.00,
                    'selling_price' => 15.00,
                    'currency' => 'usd',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 2',
                    'sku' => 'SKU-002',
                    'brand' => 'Brand B',
                    'face_value' => 25.00,
                    'cost_price' => 20.00,
                    'selling_price' => 30.00,
                    'currency' => 'usd',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 3',
                    'sku' => 'SKU-003',
                    'brand' => 'Brand C',
                    'face_value' => 40.00,
                    'cost_price' => 30.00,
                    'selling_price' => 45.00,
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
                'products.0.face_value',
            ]);
    }

    public function test_admin_create_digital_product_fails_without_face_value(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'No Face Value Product',
                    'sku' => 'SKU-NOFV-001',
                    'brand' => 'Test Brand',
                    'cost_price' => 50.00,
                    'selling_price' => 75.00,
                    'currency' => 'usd',
                    // face_value intentionally omitted
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.face_value']);

        $this->assertDatabaseMissing('digital_products', ['sku' => 'SKU-NOFV-001']);
    }

    public function test_admin_create_digital_product_fails_when_face_value_is_zero(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Zero Face Value Product',
                    'sku' => 'SKU-ZFV-001',
                    'brand' => 'Test Brand',
                    'cost_price' => 50.00,
                    'face_value' => 0,
                    'selling_price' => 75.00,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.face_value']);

        $this->assertDatabaseMissing('digital_products', ['sku' => 'SKU-ZFV-001']);
    }

    public function test_admin_create_digital_product_fails_when_face_value_is_negative(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Negative Face Value Product',
                    'sku' => 'SKU-NFV-001',
                    'brand' => 'Test Brand',
                    'cost_price' => 50.00,
                    'face_value' => -10.00,
                    'selling_price' => 75.00,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.face_value']);

        $this->assertDatabaseMissing('digital_products', ['sku' => 'SKU-NFV-001']);
    }

    public function test_admin_create_digital_product_fails_when_selling_price_is_zero(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Zero Price Product',
                    'sku' => 'SKU-ZERO-001',
                    'brand' => 'Test Brand',
                    'face_value' => 10.00,
                    'cost_price' => 10.00,
                    'selling_price' => 0,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.selling_price']);

        $this->assertDatabaseMissing('digital_products', ['sku' => 'SKU-ZERO-001']);
    }

    public function test_admin_update_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Original Name',
            'cost_price' => 100.00,
            'selling_price' => 150.00,
            'face_value' => 200.00,
            'selling_discount' => 0,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'brand' => 'Updated Brand',
            'cost_price' => 145.25,
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
                    'face_value',
                    'selling_price',
                    'selling_discount',
                    'cost_price_discount',
                    'profit_margin',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital product updated successfully.',
                'data' => [
                    'id' => $digitalProduct->id,
                    'name' => 'Updated Name',
                    'cost_price' => '145.25',
                ],
            ]);

        $this->assertDatabaseHas('digital_products', [
            'id' => $digitalProduct->id,
            'name' => 'Updated Name',
            'cost_price' => 145.25,
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

        $csvContent = "name,sku,brand,description,cost_price,face_value,selling_price,currency,region,tags,metadata\n";
        $csvContent .= "Gaming Card,SKU-GAMING-001,Nintendo,Nintendo Switch gift card,50.00,60.00,55.00,usd,US,gaming|cards,\n";
        $csvContent .= "Movie Voucher,SKU-MOVIE-001,Disney,Disney movie theater voucher,25.00,30.00,28.00,eur,EU,movies|entertainment,\n";
        $csvContent .= "Amazon Gift Card,SKU-AMAZON-001,Amazon,Amazon $100 gift card,100.00,120.00,110.00,eur,UK,shopping|cards,\n";

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

        $csvContent = "name,sku,brand,description,cost_price,face_value,currency,selling_price,region,tags,metadata\n";
        $csvContent .= "Test Card,SKU-TEST,Test Brand,Test description,50.00,60.00,invalid_currency,55.00,US,test,\n";

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

    public function test_admin_create_digital_product_with_selling_discount(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // Test case 1: Create product with 20% selling discount
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Discounted Digital Product',
                    'sku' => 'SKU-DISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 20,  // 20% discount
                    'currency' => 'usd',
                    'region' => 'US',
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
                        'selling_price',
                        'selling_discount',
                        'cost_price_discount',
                        'profit_margin',
                    ],
                ],
                'message',
            ]);

        // Verify the selling price is calculated correctly with discount
        // Expected: 100.00 * (1 - 20/100) = 100.00 * 0.8 = 80.00
        $response->assertJson([
            'error' => false,
            'data' => [
                [
                    'selling_price' => 80,
                    'selling_discount' => 20,
                    'cost_price_discount' => 50,
                    'profit_margin' => 30,
                ],
            ],
            'message' => 'Digital products created successfully.',
        ]);

        $this->assertDatabaseHas('digital_products', [
            'name' => 'Discounted Digital Product',
            'sku' => 'SKU-DISCOUNT-001',
            'selling_discount' => 20,
            'selling_price' => 100.00,  // Stored as base price
        ]);
    }

    public function test_admin_update_digital_product_with_selling_discount(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Original Product',
            'selling_price' => 100.00,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_discount' => 0,
        ]);

        $updateData = [
            'selling_discount' => 25,  // Add 25% discount
        ];

        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'selling_price',
                    'selling_discount',
                    'profit_margin',
                ],
                'message',
            ]);

        // Verify the selling price is calculated correctly with new discount
        // Expected: 100.00 * (1 - 25/100) = 100.00 * 0.75 = 75.00
        $response->assertJson([
            'error' => false,
            'data' => [
                'id' => $digitalProduct->id,
                'selling_price' => 75,
                'selling_discount' => 25,
                'profit_margin' => 25,  // (75 - 50)
            ],
            'message' => 'Digital product updated successfully.',
        ]);

        $this->assertDatabaseHas('digital_products', [
            'id' => $digitalProduct->id,
            'selling_discount' => 25,
        ]);
    }

    public function test_admin_create_digital_product_with_zero_selling_discount(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // Test with 0% discount (no discount)
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'No Discount Product',
                    'sku' => 'SKU-NODISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 70.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 0,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201);

        // With 0% discount, selling price should remain as base price
        $response->assertJson([
            'error' => false,
            'data' => [
                [
                    'selling_price' => 100.00,
                    'selling_discount' => 0,
                    'profit_margin' => 30.0,  // (100 - 70) / 100 * 100 = 30%
                ],
            ],
        ]);
    }

    public function test_admin_create_digital_product_fails_when_discount_makes_selling_price_below_cost_price(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // A 100% discount makes the effective selling price 0.00,
        // which is below the cost price of 50.00 — this must be rejected.
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Max Discount Product',
                    'sku' => 'SKU-MAXDISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 100,  // Effective price: 100 * (1 - 100/100) = 0.00 < cost_price 50.00
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.selling_price']);

        $this->assertDatabaseMissing('digital_products', ['sku' => 'SKU-MAXDISCOUNT-001']);
    }

    public function test_admin_create_digital_product_fails_with_invalid_selling_discount(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // Test with selling_discount > 100
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Invalid Discount Product',
                    'sku' => 'SKU-INVALIDDISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 150,  // Invalid: > 100
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.selling_discount']);
    }

    public function test_admin_create_digital_product_with_negative_selling_discount_applies_markup(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // -10% discount means a 10% markup: face_value 100 * (1 - (-10/100)) = 110.00
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Markup Product',
                    'sku' => 'SKU-MARKUP-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => -10,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    [
                        'selling_price' => 110.00,
                        'selling_discount' => -10,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('digital_products', [
            'sku' => 'SKU-MARKUP-001',
            'selling_discount' => -10,
            'selling_price' => 100.00,  // base stored price unchanged
        ]);
    }

    public function test_positive_discount_reduces_selling_price_below_face_value(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // 20% discount: 100 * (1 - 20/100) = 80.00
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Positive Discount Product',
                    'sku' => 'SKU-POSDISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 20,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    [
                        'selling_price' => 80.00,
                        'selling_discount' => 20,
                    ],
                ],
            ]);
    }

    public function test_zero_discount_keeps_selling_price_equal_to_face_value(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // 0% discount: 100 * (1 - 0/100) = 100.00
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Zero Discount Product',
                    'sku' => 'SKU-ZERODISCOUNT-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => 0,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    [
                        'selling_price' => 100.00,
                        'selling_discount' => 0,
                    ],
                ],
            ]);
    }

    public function test_negative_discount_markup_raises_selling_price_above_face_value(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        // -15% discount (15% markup): 100 * (1 - (-15)/100) = 115.00
        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Negative Discount Markup Product',
                    'sku' => 'SKU-NEG15-001',
                    'brand' => 'Test Brand',
                    'face_value' => 100.00,
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'selling_discount' => -15,
                    'currency' => 'usd',
                ],
            ],
        ];

        $response = $this->postJson('/api/digital-products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    [
                        'selling_price' => 115.00,
                        'selling_discount' => -15,
                    ],
                ],
            ]);
    }

    public function test_update_digital_product_positive_discount(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $digitalProduct = \App\Models\DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 200.00,
            'cost_price' => 100.00,
            'selling_price' => 200.00,
            'selling_discount' => 0,
        ]);

        // Apply 25% discount: 200 * (1 - 25/100) = 150.00
        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", [
            'selling_discount' => 25,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'selling_price' => 150.00,
                    'selling_discount' => 25,
                ],
            ]);
    }

    public function test_update_digital_product_zero_discount(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $digitalProduct = \App\Models\DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 200.00,
            'cost_price' => 100.00,
            'selling_price' => 200.00,
            'selling_discount' => 25,
        ]);

        // Remove discount (set to 0): 200 * (1 - 0/100) = 200.00
        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", [
            'selling_discount' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'selling_price' => 200.00,
                    'selling_discount' => 0,
                ],
            ]);
    }

    public function test_update_digital_product_negative_discount_markup(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $digitalProduct = \App\Models\DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 200.00,
            'cost_price' => 100.00,
            'selling_price' => 200.00,
            'selling_discount' => 0,
        ]);

        // Apply -10% (10% markup): 200 * (1 - (-10)/100) = 220.00
        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", [
            'selling_discount' => -10,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'selling_price' => 220.00,
                    'selling_discount' => -10,
                ],
            ]);
    }

    public function test_update_digital_product_discount_to_max_markup_boundary(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $digitalProduct = \App\Models\DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 0,
        ]);

        // -100% discount = 100% markup: 100 * (1 - (-100)/100) = 200.00
        $response = $this->postJson("/api/digital-products/{$digitalProduct->id}", [
            'selling_discount' => -100,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'selling_price' => 200.00,
                    'selling_discount' => -100,
                ],
            ]);
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
