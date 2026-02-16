<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRulePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private string $endpoint = '/api/price-rules/preview';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function actingAsAdmin(): void
    {
        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($this->adminUser);
    }

    public function test_preview_shows_products_affected_by_percentage_increase(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sony']);
        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'PlayStation 5',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'PlayStation Gift Card',
            'face_value' => 50.00,
            'selling_price' => 50.00,
        ]);

        $payload = [
            'name' => 'Test Preview',
            'description' => 'Preview test',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'error' => false,
            'message' => 'Price rule preview retrieved successfully.',
        ]);

        $preview = $response->json('data');
        $this->assertCount(2, $preview);

        // Verify product 1 calculation: 100 + (100 * 0.1) = 110
        $product1Preview = collect($preview)->firstWhere('product_id', $product1->id);
        $this->assertEquals(110.00, $product1Preview['new_selling_price']);
        $this->assertEquals(100.00, $product1Preview['current_selling_price']);

        // Verify product 2 calculation: 50 + (50 * 0.1) = 55
        $product2Preview = collect($preview)->firstWhere('product_id', $product2->id);
        $this->assertEquals(55.00, $product2Preview['new_selling_price']);
        $this->assertEquals(50.00, $product2Preview['current_selling_price']);
    }

    public function test_preview_shows_products_affected_by_percentage_decrease(): void
    {
        $brand = Brand::factory()->create(['name' => 'Microsoft']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Xbox Gift Card',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $payload = [
            'name' => 'Test Preview Decrease',
            'description' => 'Preview test decrease',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 20,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 100 - (100 * 0.2) = 80
        $productPreview = collect($preview)->firstWhere('product_id', $product->id);
        $this->assertEquals(80.00, $productPreview['new_selling_price']);
    }

    public function test_preview_shows_products_affected_by_absolute_increase(): void
    {
        $brand = Brand::factory()->create(['name' => 'Apple']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Apple Gift Card',
            'face_value' => 50.00,
            'selling_price' => 50.00,
        ]);

        $payload = [
            'name' => 'Test Absolute',
            'description' => 'Preview test absolute',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 15,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 50 + 15 = 65
        $productPreview = collect($preview)->firstWhere('product_id', $product->id);
        $this->assertEquals(65.00, $productPreview['new_selling_price']);
    }

    public function test_preview_shows_products_affected_by_absolute_decrease(): void
    {
        $brand = Brand::factory()->create(['name' => 'Google']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Google Play Card',
            'face_value' => 30.00,
            'selling_price' => 30.00,
        ]);

        $payload = [
            'name' => 'Test Absolute Decrease',
            'description' => 'Preview test absolute decrease',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 10,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 30 - 10 = 20
        $productPreview = collect($preview)->firstWhere('product_id', $product->id);
        $this->assertEquals(20.00, $productPreview['new_selling_price']);
    }

    public function test_preview_with_multiple_conditions_all_match(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nintendo']);
        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Nintendo Switch Game',
            'face_value' => 60.00,
            'selling_price' => 60.00,
            'status' => 'active',
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Nintendo Inactive Game',
            'face_value' => 60.00,
            'selling_price' => 60.00,
            'status' => 'inactive',
        ]);

        $payload = [
            'name' => 'Test Multiple Conditions',
            'description' => 'Preview test',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::EQUAL->value,
                    'value' => '60.00',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Should only return product1 as product2 has status = 0
        $this->assertCount(2, $preview);
        $this->assertEquals($product1->id, $preview[0]['product_id']);
        $this->assertEquals(63.00, $preview[0]['new_selling_price']);
    }

    public function test_preview_with_multiple_conditions_any_match(): void
    {
        $brand1 = Brand::factory()->create(['name' => 'Samsung']);
        $brand2 = Brand::factory()->create(['name' => 'LG']);
        $product1 = Product::factory()->create([
            'brand_id' => $brand1->id,
            'name' => 'Samsung TV',
            'face_value' => 500.00,
            'selling_price' => 500.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'name' => 'LG TV',
            'face_value' => 400.00,
            'selling_price' => 400.00,
        ]);
        $product3 = Product::factory()->create([
            'brand_id' => Brand::factory()->create(['name' => 'Other'])->id,
            'name' => 'Other TV',
            'face_value' => 300.00,
            'selling_price' => 300.00,
        ]);

        $payload = [
            'name' => 'Test Any Match',
            'description' => 'Preview test any',
            'match_type' => 'any',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand1->id,
                ],
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand2->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Should return product1 and product2
        $this->assertCount(2, $preview);
        $ids = array_column($preview, 'product_id');
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
        $this->assertNotContains($product3->id, $ids);
    }

    public function test_preview_returns_empty_when_no_products_match(): void
    {
        $brand = Brand::factory()->create(['name' => 'NonExistent']);

        $payload = [
            'name' => 'Test Empty',
            'description' => 'Preview test empty',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        $this->assertEmpty($preview);
    }

    public function test_preview_does_not_update_database(): void
    {
        $brand = Brand::factory()->create(['name' => 'Test Brand']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Test Product',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $originalPrice = $product->selling_price;

        $payload = [
            'name' => 'Test No Update',
            'description' => 'Preview test',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 50,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $this->postJson($this->endpoint, $payload);

        // Verify the product's actual price in database hasn't changed
        $product->refresh();
        $this->assertEquals($originalPrice, $product->selling_price);
    }

    public function test_preview_includes_all_required_fields(): void
    {
        $brand = Brand::factory()->create(['name' => 'Complete']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Complete Product',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $payload = [
            'name' => 'Test Complete',
            'description' => 'Preview test',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => 'active',
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand->id,
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        $this->assertCount(1, $preview);
        $item = $preview[0];
        $this->assertArrayHasKey('product_id', $item);
        $this->assertArrayHasKey('product_name', $item);
        $this->assertArrayHasKey('face_value', $item);
        $this->assertArrayHasKey('current_selling_price', $item);
        $this->assertArrayHasKey('new_selling_price', $item);
    }
}
