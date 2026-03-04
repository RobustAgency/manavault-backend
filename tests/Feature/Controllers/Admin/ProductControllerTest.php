<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use App\Models\PriceRule;
use App\Models\DigitalProduct;
use App\Models\PriceRuleProduct;
use Illuminate\Http\UploadedFile;
use App\Enums\PriceRule\ActionMode;
use App\Services\PricingRuleService;
use App\Enums\Product\FulfillmentMode;
use App\Enums\PriceRule\ActionOperator;
use Illuminate\Support\Facades\Storage;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\WithFaker;
use App\Enums\PriceRule\Status as PriceRuleStatus;
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
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'brand',
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
                    'per_page',
                    'total',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ]);
    }

    public function test_admin_list_products_with_status_filter(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(3)->create(['status' => 'active']);
        Product::factory()->count(2)->create(['status' => 'inactive']);

        $response = $this->getJson('/api/products?status=active');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_admin_list_products_with_pagination(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/products?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 25);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_admin_show_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'selling_price' => 99.99,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand',
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
                    'selling_price' => 99.99,
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
            'selling_price' => 149.99,
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
                    'selling_price' => 149.99,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'description' => 'Product description',
            'selling_price' => 149.99,
            'currency' => 'usd',
        ]);
    }

    public function test_admin_update_product(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();

        $product = Product::factory()->create([
            'name' => 'Original Name',
            'selling_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'brand_id' => $brand->id,
            'short_description' => 'Updated short desc',
            'selling_price' => 199.99,
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
                    'selling_price' => 199.99,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'selling_price' => 199.99,
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

    public function test_show_product_returns_discounted_selling_price_when_active_price_rule_applied(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Discount Rule',
            'description' => '10% discount',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => PriceRuleStatus::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand->id],
            ],
        ];

        app(PricingRuleService::class)->createPriceRuleWithConditions($data);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertEquals(90.00, $response->json('data.selling_price')); // 100 - 10% = 90
    }

    public function test_show_product_returns_marked_up_selling_price_when_active_price_rule_applied(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Markup Rule',
            'description' => 'Fixed $25 markup',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 25,
            'status' => PriceRuleStatus::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand->id],
            ],
        ];

        app(PricingRuleService::class)->createPriceRuleWithConditions($data);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertEquals(125.00, $response->json('data.selling_price')); // 100 + 25 = 125
    }

    public function test_show_product_returns_original_selling_price_when_no_price_rule_applied(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'face_value' => 100.00,
            'selling_price' => 79.99,
        ]);

        // No PriceRuleProduct records exist
        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertEquals(79.99, $response->json('data.selling_price'));
    }

    public function test_show_product_ignores_inactive_price_rule(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $inactiveRule = PriceRule::factory()->create(['status' => PriceRuleStatus::INACTIVE->value]);

        PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $inactiveRule->id,
            'final_selling_price' => 50.00,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertEquals(100.00, $response->json('data.selling_price')); // inactive rule must be ignored
    }

    public function test_show_product_returns_latest_rule_price_when_multiple_applied(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $activeRule = PriceRule::factory()->create(['status' => PriceRuleStatus::ACTIVE->value]);

        // Older rule application
        PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $activeRule->id,
            'final_selling_price' => 80.00,
            'updated_at' => now()->subMinutes(10),
        ]);

        // Latest rule application
        PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $activeRule->id,
            'final_selling_price' => 90.00,
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertEquals(90.00, $response->json('data.selling_price')); // latest record wins
    }

    public function test_list_products_returns_discounted_selling_price_in_each_item(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 200.00,
            'selling_price' => 200.00,
        ]);

        $data = [
            'name' => 'List Test Rule',
            'description' => '5% discount',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => PriceRuleStatus::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand->id],
            ],
        ];

        app(PricingRuleService::class)->createPriceRuleWithConditions($data);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);

        $items = $response->json('data.data');
        $item = collect($items)->firstWhere('id', $product->id);

        $this->assertNotNull($item);
        $this->assertEquals(190.00, $item['selling_price']); // 200 - 5% = 190
    }

    public function test_selling_price_updates_correctly_after_rule_is_updated(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $priceRule = PriceRule::factory()->create([
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => PriceRuleStatus::ACTIVE->value,
        ]);

        $service = app(PricingRuleService::class);

        // Apply initial rule: 100 - 10% = 90
        $service->updatePriceRuleWithConditions($priceRule, [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => PriceRuleStatus::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand->id],
            ],
        ]);

        $response = $this->getJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $this->assertEquals(90.00, $response->json('data.selling_price'));

        // Update rule to 20% discount: 100 - 20% = 80
        $service->updatePriceRuleWithConditions($priceRule, [
            'name' => 'Rule Updated',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 20,
            'status' => PriceRuleStatus::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand->id],
            ],
        ]);

        $response = $this->getJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $this->assertEquals(80.00, $response->json('data.selling_price'));
    }
}
