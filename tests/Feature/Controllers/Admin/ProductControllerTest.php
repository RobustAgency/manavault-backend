<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Http\UploadedFile;
use App\Enums\Product\FulfillmentMode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_admin_list_products(): void
    {

        $this->actingAs($this->admin);

        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'brand_id',
                        'description',
                        'short_description',
                        'long_description',
                        'tags',
                        'image',
                        'selling_price',
                        'currency',
                        'status',
                        'regions',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'error',
                'message',
                'links',
                'meta',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ]);
    }

    public function test_admin_list_products_with_status_filter(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create(['currency' => 'usd']);

        $product1 = Product::factory()->create(['status' => 'active']);
        $product2 = Product::factory()->create(['status' => 'inactive']);

        $product1->digitalProducts()->syncWithoutDetaching([$digitalProduct->id]);
        $product2->digitalProducts()->syncWithoutDetaching([$digitalProduct->id]);

        $response = $this->getJson('/api/products?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_list_products_with_pagination(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/products?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_admin_show_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'face_value' => 100.00,
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'face_value' => 100.00,
            'selling_price' => 99.01,
            'currency' => 'usd',
            'selling_discount' => 0.99,
        ]);

        $product->digitalProducts()->attach($digitalProduct->id);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand_id',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'selling_price',
                    'currency',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product retrieved successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Test Product',
                    'selling_price' => 99.01,
                ],
            ]);
    }

    public function test_admin_show_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/products/999999');

        $response->assertStatus(404);
    }

    public function test_admin_create_product(): void
    {
        $this->actingAs($this->admin);

        Storage::fake('public');

        $brand = Brand::factory()->create();

        $data = [
            'name' => 'New Product',
            'description' => 'Product description',
            'sku' => 'SKU-12345',
            'brand_id' => $brand->id,
            'short_description' => 'Short description',
            'long_description' => 'Long description with more details',
            'tags' => ['gaming', 'digital'],
            'image' => UploadedFile::fake()->image('product.jpg'),
            'face_value' => 120.00,
            'currency' => 'usd',
            'status' => 'active',
            'regions' => ['US', 'CA'],
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand_id',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'face_value',
                    'selling_price',
                    'currency',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product created successfully.',
                'data' => [
                    'name' => 'New Product',
                    'description' => 'Product description',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'description' => 'Product description',
            'currency' => 'usd',
        ]);
    }

    public function test_admin_update_product(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();

        $product = Product::factory()->create([
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'brand_id' => $brand->id,
            'short_description' => 'Updated short desc',
        ];

        $response = $this->postJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand_id',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'face_value',
                    'selling_price',
                    'currency',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product updated successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Updated Name',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);
    }

    public function test_admin_delete_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_admin_delete_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/products/999999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_access_products(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_create_product(): void
    {
        $data = [
            'name' => 'Test Product',
            'selling_price' => 99.99,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(401);
    }

    public function test_list_products_validates_pagination_limits(): void
    {
        $this->actingAs($this->admin);

        // Test per_page exceeds maximum
        $response = $this->getJson('/api/products?per_page=150');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_assign_digital_products_to_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['currency' => 'usd']);
        $digitalProducts = DigitalProduct::factory()->count(3)->create(['currency' => 'usd']);

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => $digitalProducts->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'error',
            'message',
        ]);
        $response->assertJsonPath('error', false);

        foreach ($digitalProducts as $digitalProduct) {
            $this->assertDatabaseHas('product_supplier', [
                'product_id' => $product->id,
                'digital_product_id' => $digitalProduct->id,
            ]);
        }
    }

    public function test_assign_digital_products_missing_required_field(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids']);
    }

    public function test_assign_digital_products_empty_array(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids']);
    }

    public function test_assign_digital_products_with_currency_mismatch(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['currency' => 'usd']);
        $digitalProductUsd = DigitalProduct::factory()->create(['currency' => 'usd']);
        $digitalProductEur = DigitalProduct::factory()->create(['currency' => 'eur']);

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => [$digitalProductUsd->id, $digitalProductEur->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids.1']);

        // Verify the error message mentions currency mismatch
        $errors = $response->json('errors');
        $this->assertStringContainsString('currency', $errors['digital_product_ids.1'][0]);
    }

    public function test_assign_digital_products_all_matching_currency(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['currency' => 'gbp']);
        $digitalProducts = DigitalProduct::factory()->count(3)->create(['currency' => 'gbp']);

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => $digitalProducts->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('error', false);

        foreach ($digitalProducts as $digitalProduct) {
            $this->assertDatabaseHas('product_supplier', [
                'product_id' => $product->id,
                'digital_product_id' => $digitalProduct->id,
            ]);
        }
    }

    public function test_assign_digital_products_with_invalid_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products/999999/digital_products', [
            'digital_product_ids' => [1, 2, 3],
        ]
        );

        $response->assertStatus(404);
    }

    public function test_assign_digital_products_with_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['currency' => 'usd']);
        $validDigitalProduct = DigitalProduct::factory()->create(['currency' => 'usd']);

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => [$validDigitalProduct->id, 999999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids.1']);
    }

    public function test_assign_digital_products_with_invalid_data_type(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids']);
    }

    public function test_assign_digital_products_with_non_integer_ids(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->postJson('/api/products/'.$product->id.'/digital_products', [
            'digital_product_ids' => ['one', 'two', 'three'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_product_ids.0', 'digital_product_ids.1', 'digital_product_ids.2']);
    }

    public function test_update_product_with_custom_priority_sets_manual_fulfillment_mode(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'name' => 'Updated Product',
            'sku' => 'SKU-UPDATED-001',
            'brand_id' => $brand->id,
            'selling_price' => 99.99,
            'status' => 'active',
            'is_custom_priority' => true,
        ];

        $response = $this->postJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonPath('data.is_custom_priority', true);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $data['name'],
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
    }

    public function test_update_product_without_custom_priority_sets_price_fulfillment_mode(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);

        $data = [
            'name' => 'Updated Product',
            'sku' => 'SKU-UPDATED-002',
            'brand_id' => $brand->id,
            'selling_price' => 79.99,
            'status' => 'active',
            'is_custom_priority' => false,
        ];

        $response = $this->postJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonPath('data.is_custom_priority', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $data['name'],
            'fulfillment_mode' => FulfillmentMode::PRICE->value,
        ]);
    }

    public function test_remove_digital_product_from_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $response = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProduct->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Digital product removed successfully.',
            ]);

        $this->assertDatabaseMissing('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProduct->id,
        ]);
    }

    public function test_remove_digital_product_removes_only_specified_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach([
            $digitalProduct1->id => ['priority' => 1],
            $digitalProduct2->id => ['priority' => 2],
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProduct1->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProduct1->id,
        ]);

        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProduct2->id,
        ]);
    }

    public function test_remove_digital_product_from_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create();

        $response = $this->deleteJson("/api/products/99999/digital_products/{$digitalProduct->id}");

        $response->assertStatus(404);
    }

    public function test_remove_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}/digital_products/99999");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Digital product removed successfully.',
            ]);
    }

    public function test_remove_digital_product_requires_authentication(): void
    {
        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $response = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProduct->id}");

        $response->assertStatus(401);
    }

    public function test_remove_digital_product_requires_update_product_permission(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $response = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProduct->id}");

        $response->assertStatus(403);
    }

    public function test_remove_digital_product_with_multiple_removals(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProducts = DigitalProduct::factory()->count(3)->create();

        $product->digitalProducts()->attach([
            $digitalProducts[0]->id => ['priority' => 1],
            $digitalProducts[1]->id => ['priority' => 2],
            $digitalProducts[2]->id => ['priority' => 3],
        ]);

        // Remove first product
        $response1 = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProducts[0]->id}");
        $response1->assertStatus(200);

        // Remove second product
        $response2 = $this->deleteJson("/api/products/{$product->id}/digital_products/{$digitalProducts[1]->id}");
        $response2->assertStatus(200);

        // Verify only third product remains
        $this->assertDatabaseMissing('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[0]->id,
        ]);

        $this->assertDatabaseMissing('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[1]->id,
        ]);

        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[2]->id,
        ]);
    }

    public function test_admin_batch_import_products_from_csv(): void
    {
        $this->actingAs($this->admin);

        $csvContent = "name,sku,brand_id,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Gaming Card,SKU-GAMING-001,1,Gaming product,Short desc,Long description,50.00,usd,active,"[""gaming""]","[""US""]"'."\n";
        $csvContent .= 'Movie Voucher,SKU-MOVIE-001,2,Movie product,Short desc,Long description,25.00,eur,active,"[""movies""]","[""EU""]"'."\n";
        $csvContent .= 'Amazon Gift Card,SKU-AMAZON-001,3,Amazon gift card,Short desc,Long description,100.00,eur,active,"[""shopping""]","[""UK""]"'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');

        $uploadedFile = new UploadedFile($tempFile, 'products.csv', 'text/csv', null, true);

        $response = $this->postJson('/api/products/batch-import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Products imported successfully.',
            ]);

        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseHas('products', [
            'name' => 'Gaming Card',
            'sku' => 'SKU-GAMING-001',
            'face_value' => 50.00,
            'currency' => 'usd',
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Movie Voucher',
            'sku' => 'SKU-MOVIE-001',
            'face_value' => 25.00,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Amazon Gift Card',
            'sku' => 'SKU-AMAZON-001',
            'face_value' => 100.00,
            'currency' => 'eur',
        ]);
    }

    public function test_admin_batch_import_products_fails_without_authentication(): void
    {
        $csvContent = "name,sku,brand_id,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Test Product,SKU-TEST-001,1,Test,Short,Long,50.00,usd,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = new UploadedFile($tempFile, 'products.csv', 'text/csv', null, true);

        $response = $this->postJson('/api/products/batch-import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_batch_import_products_fails_without_file(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products/batch-import', []);

        $response->assertStatus(422);
    }

    public function test_admin_batch_import_products_fails_with_invalid_csv_format(): void
    {
        $this->actingAs($this->admin);

        $csvContent = "name,sku,brand_id,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Invalid Product,SKU-001,,Description,Short,Long,,invalid_currency,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = new UploadedFile($tempFile, 'products.csv', 'text/csv', null, true);

        $response = $this->postJson('/api/products/batch-import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(500)
            ->assertJson(['error' => true]);
    }

    /**
     * Test: Digital products are displayed in correct order with PRICE fulfillment mode (sorted by cost_price ASC)
     */
    public function test_digital_products_displayed_in_cost_price_order_on_show(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $supplier1 = \App\Models\Supplier::factory()->create(['name' => 'Supplier 1']);
        $supplier2 = \App\Models\Supplier::factory()->create(['name' => 'Supplier 2']);
        $supplier3 = \App\Models\Supplier::factory()->create(['name' => 'Supplier 3']);

        // Create a product with PRICE fulfillment mode
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Multi-Supplier Product',
            'face_value' => 100.00,
            'fulfillment_mode' => FulfillmentMode::PRICE->value,
            'currency' => 'usd',
        ]);

        // Create digital products with different cost prices
        // We deliberately create them in reverse order to verify sorting
        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier3->id,
            'name' => 'Product from Supplier 3',
            'cost_price' => 85.00,
            'selling_price' => 95.00,
            'currency' => 'usd',
        ]);

        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'Product from Supplier 1',
            'cost_price' => 50.00,
            'selling_price' => 70.00,
            'currency' => 'usd',
        ]);

        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'name' => 'Product from Supplier 2',
            'cost_price' => 70.00,
            'selling_price' => 85.00,
            'currency' => 'usd',
        ]);

        // Attach in random order to test sorting
        $product->digitalProducts()->attach([$dp3->id, $dp1->id, $dp2->id]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $digitalProducts = $response->json('data.digital_products');
        $this->assertCount(3, $digitalProducts);

        // Verify digital products are sorted by cost_price ASC
        $this->assertEquals($dp1->id, $digitalProducts[0]['id']);
        $this->assertEquals(50.00, $digitalProducts[0]['cost_price']);

        $this->assertEquals($dp2->id, $digitalProducts[1]['id']);
        $this->assertEquals(70.00, $digitalProducts[1]['cost_price']);

        $this->assertEquals($dp3->id, $digitalProducts[2]['id']);
        $this->assertEquals(85.00, $digitalProducts[2]['cost_price']);
    }

    /**
     * Test: Digital products are displayed in correct order with MANUAL fulfillment mode (sorted by priority)
     */
    public function test_digital_products_displayed_in_priority_order_on_show(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $supplier1 = \App\Models\Supplier::factory()->create(['name' => 'Supplier A']);
        $supplier2 = \App\Models\Supplier::factory()->create(['name' => 'Supplier B']);
        $supplier3 = \App\Models\Supplier::factory()->create(['name' => 'Supplier C']);

        // Create a product with MANUAL fulfillment mode
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Priority-based Product',
            'face_value' => 100.00,
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
            'currency' => 'usd',
        ]);

        // Create digital products
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'Priority Product 1',
            'cost_price' => 50.00,
            'selling_price' => 70.00,
            'currency' => 'usd',
        ]);

        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'name' => 'Priority Product 2',
            'cost_price' => 70.00,
            'selling_price' => 85.00,
            'currency' => 'usd',
        ]);

        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier3->id,
            'name' => 'Priority Product 3',
            'cost_price' => 85.00,
            'selling_price' => 95.00,
            'currency' => 'usd',
        ]);

        // Attach with specific priorities (reverse order to test)
        $product->digitalProducts()->attach([
            $dp3->id => ['priority' => 3],
            $dp1->id => ['priority' => 1],
            $dp2->id => ['priority' => 2],
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $digitalProducts = $response->json('data.digital_products');
        $this->assertCount(3, $digitalProducts);

        // Verify digital products are sorted by priority ASC
        $this->assertEquals($dp1->id, $digitalProducts[0]['id']);
        $this->assertEquals(1, $digitalProducts[0]['pivot']['priority']);

        $this->assertEquals($dp2->id, $digitalProducts[1]['id']);
        $this->assertEquals(2, $digitalProducts[1]['pivot']['priority']);

        $this->assertEquals($dp3->id, $digitalProducts[2]['id']);
        $this->assertEquals(3, $digitalProducts[2]['pivot']['priority']);
    }

    /**
     * Test: Single digital product selected based on PRICE fulfillment mode (lowest cost)
     */
    public function test_digital_product_selection_with_price_mode(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $supplier1 = \App\Models\Supplier::factory()->create();
        $supplier2 = \App\Models\Supplier::factory()->create();
        $supplier3 = \App\Models\Supplier::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'fulfillment_mode' => FulfillmentMode::PRICE->value,
            'currency' => 'usd',
        ]);

        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'cost_price' => 100.00,
            'selling_price' => 120.00,
            'currency' => 'usd',
        ]);

        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'cost_price' => 50.00,
            'selling_price' => 70.00,
            'currency' => 'usd',
        ]);

        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier3->id,
            'cost_price' => 75.00,
            'selling_price' => 90.00,
            'currency' => 'usd',
        ]);

        $product->digitalProducts()->attach([$dp1->id, $dp2->id, $dp3->id]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        // The single digital_product should be the one with lowest cost_price
        $selectedDigitalProduct = $response->json('data.digital_product');
        $this->assertEquals($dp2->id, $selectedDigitalProduct['id']);
        $this->assertEquals(50.00, $selectedDigitalProduct['cost_price']);
    }

    /**
     * Test: Single digital product selected based on MANUAL fulfillment mode (priority 1)
     */
    public function test_digital_product_selection_with_manual_mode(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $supplier1 = \App\Models\Supplier::factory()->create();
        $supplier2 = \App\Models\Supplier::factory()->create();
        $supplier3 = \App\Models\Supplier::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
            'currency' => 'usd',
        ]);

        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'cost_price' => 50.00,
            'selling_price' => 70.00,
            'currency' => 'usd',
        ]);

        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'cost_price' => 100.00,
            'selling_price' => 120.00,
            'currency' => 'usd',
        ]);

        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier3->id,
            'cost_price' => 75.00,
            'selling_price' => 90.00,
            'currency' => 'usd',
        ]);

        // Attach with priorities (not in order)
        $product->digitalProducts()->attach([
            $dp3->id => ['priority' => 2],
            $dp1->id => ['priority' => 3],
            $dp2->id => ['priority' => 1],
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        // The single digital_product should be the one with priority 1
        $selectedDigitalProduct = $response->json('data.digital_product');
        $this->assertEquals($dp2->id, $selectedDigitalProduct['id']);
        $this->assertEquals(1, $selectedDigitalProduct['pivot']['priority']);
    }

    /**
     * Test: Verify selling_price is correctly returned from the selected digital product
     */
    public function test_product_selling_price_from_digital_product(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $supplier1 = \App\Models\Supplier::factory()->create();
        $supplier2 = \App\Models\Supplier::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'fulfillment_mode' => FulfillmentMode::PRICE->value,
            'currency' => 'usd',
        ]);

        // Create digital products with different selling prices
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'face_value' => 160.00,
            'cost_price' => 140.00,
            'selling_price' => 150.00,
            'currency' => 'usd',
        ]);

        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 85.00,
            'selling_discount' => 15.00,
            'currency' => 'usd',
        ]);

        $product->digitalProducts()->attach([$dp1->id, $dp2->id]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        // Product selling_price should match the selected digital product (lowest cost)
        $this->assertEquals(85.00, $response->json('data.selling_price'));
        $this->assertEquals($dp2->id, $response->json('data.digital_product.id'));
    }

    /**
     * Test 1: Get all unique regions from products
     */
    public function test_get_all_regions_from_products(): void
    {
        $this->actingAs($this->admin);

        // Create products with different regions (factory handles other fields)
        Product::factory()->create(['regions' => ['US', 'CA']]);
        Product::factory()->create(['regions' => ['EU', 'UK']]);
        Product::factory()->create(['regions' => ['US', 'EU', 'AU']]);
        Product::factory()->create(['regions' => ['JP', 'KR']]);

        // Create a product with null regions (should be excluded)
        Product::factory()->create(['regions' => null]);

        // Create a product with empty regions array (should be excluded)
        Product::factory()->create(['regions' => []]);

        $response = $this->getJson('/api/products/regions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data',
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Regions retrieved successfully.',
            ]);

        $regions = $response->json('data');

        // Verify all unique regions are returned
        $this->assertCount(7, $regions);

        // Verify all expected regions are present (order doesn't matter)
        $this->assertContains('AU', $regions);
        $this->assertContains('CA', $regions);
        $this->assertContains('EU', $regions);
        $this->assertContains('JP', $regions);
        $this->assertContains('KR', $regions);
        $this->assertContains('UK', $regions);
        $this->assertContains('US', $regions);
    }

    /**
     * Test 3: Filter products by region parameter
     */
    public function test_filter_products_by_region(): void
    {
        $this->actingAs($this->admin);

        // Create products with different regions (factory handles other fields)
        Product::factory()->create([
            'name' => 'US Product',
            'regions' => ['US', 'CA'],
        ]);

        Product::factory()->create([
            'name' => 'EU Product',
            'regions' => ['EU', 'UK'],
        ]);

        Product::factory()->create([
            'name' => 'Global Product',
            'regions' => ['US', 'EU', 'AU'],
        ]);

        Product::factory()->create([
            'name' => 'Asia Product',
            'regions' => ['JP', 'KR'],
        ]);

        // Create a product without regions
        Product::factory()->create([
            'name' => 'No Region Product',
            'regions' => null,
        ]);

        // Filter by US region
        $response = $this->getJson('/api/products?region=US');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ]);

        $products = $response->json('data');

        // Should return only products containing "US" in regions
        $this->assertCount(2, $products);

        $productNames = array_column($products, 'name');
        $this->assertContains('US Product', $productNames);
        $this->assertContains('Global Product', $productNames);
        $this->assertNotContains('EU Product', $productNames);
        $this->assertNotContains('Asia Product', $productNames);
        $this->assertNotContains('No Region Product', $productNames);

        // Filter by EU region
        $response2 = $this->getJson('/api/products?region=EU');

        $products2 = $response2->json('data');
        $this->assertCount(2, $products2);

        $productNames2 = array_column($products2, 'name');
        $this->assertContains('EU Product', $productNames2);
        $this->assertContains('Global Product', $productNames2);

        // Filter by JP region
        $response3 = $this->getJson('/api/products?region=JP');

        $products3 = $response3->json('data');
        $this->assertCount(1, $products3);
        $this->assertEquals('Asia Product', $products3[0]['name']);

        // Filter by non-existent region
        $response4 = $this->getJson('/api/products?region=XYZ');

        $products4 = $response4->json('data');
        $this->assertCount(0, $products4);
    }

    /**
     * Helper method to create a temporary file with specific content and extension
     */
    private function createTempFile(string $content, string $extension): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'product_test_').'.'.$extension;
        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}
