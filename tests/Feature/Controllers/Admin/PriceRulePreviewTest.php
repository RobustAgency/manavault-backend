<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRulePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Supplier $supplier;

    private string $endpoint = '/api/price-rules/preview';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->supplier = Supplier::factory()->create();
    }

    private function actingAsAdmin(): void
    {
        $this->adminUser = User::factory()->create(['role' => 'super_admin', 'is_approved' => true]);
        $this->actingAs($this->adminUser);
    }

    public function test_preview_shows_products_affected_by_percentage_increase(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'PlayStation 5',
            'brand' => 'Sony',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Sony',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Sony',
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

        // Verify digital product 1 calculation: 100 + (100 * 0.1) = 110
        $dp1Preview = collect($preview)->firstWhere('digital_product_id', $dp1->id);
        $this->assertEquals(110.00, $dp1Preview['new_selling_price']);
        $this->assertEquals(100.00, $dp1Preview['current_selling_price']);

        // Verify digital product 2 calculation: 50 + (50 * 0.1) = 55
        $dp2Preview = collect($preview)->firstWhere('digital_product_id', $dp2->id);
        $this->assertEquals(55.00, $dp2Preview['new_selling_price']);
        $this->assertEquals(50.00, $dp2Preview['current_selling_price']);
    }

    public function test_preview_shows_products_affected_by_percentage_decrease(): void
    {
        $dp = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Xbox Gift Card',
            'brand' => 'Microsoft',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Microsoft',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 100 - (100 * 0.2) = 80
        $dpPreview = collect($preview)->firstWhere('digital_product_id', $dp->id);
        $this->assertEquals(80.00, $dpPreview['new_selling_price']);
    }

    public function test_preview_shows_products_affected_by_absolute_increase(): void
    {
        $dp = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Apple Gift Card',
            'brand' => 'Apple',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Apple',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 50 + 15 = 65
        $dpPreview = collect($preview)->firstWhere('digital_product_id', $dp->id);
        $this->assertEquals(65.00, $dpPreview['new_selling_price']);
    }

    public function test_preview_shows_products_affected_by_absolute_decrease(): void
    {
        $dp = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Google Play Card',
            'brand' => 'Google',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Google',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Verify calculation: 30 - 10 = 20
        $dpPreview = collect($preview)->firstWhere('digital_product_id', $dp->id);
        $this->assertEquals(20.00, $dpPreview['new_selling_price']);
    }

    public function test_preview_with_multiple_conditions_all_match(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Nintendo Switch Game',
            'brand' => 'Nintendo',
            'face_value' => 60.00,
            'selling_price' => 60.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Nintendo Inactive Game',
            'brand' => 'Nintendo',
            'face_value' => 60.00,
            'selling_price' => 60.00,
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Nintendo',
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

        // Both digital products match all conditions
        $this->assertCount(2, $preview);
        $this->assertEquals(63.00, $preview[0]['new_selling_price']);
    }

    public function test_preview_with_multiple_conditions_any_match(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Samsung TV',
            'brand' => 'Samsung',
            'face_value' => 500.00,
            'selling_price' => 500.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'LG TV',
            'brand' => 'LG',
            'face_value' => 400.00,
            'selling_price' => 400.00,
        ]);
        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Other TV',
            'brand' => 'Other',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Samsung',
                ],
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'LG',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        // Should return dp1 and dp2
        $this->assertCount(2, $preview);
        $ids = array_column($preview, 'digital_product_id');
        $this->assertContains($dp1->id, $ids);
        $this->assertContains($dp2->id, $ids);
        $this->assertNotContains($dp3->id, $ids);
    }

    public function test_preview_returns_empty_when_no_products_match(): void
    {
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'NonExistentBrand',
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
        $dp = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Test Product',
            'brand' => 'Test Brand',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $originalPrice = $dp->selling_price;

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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Test Brand',
                ],
            ],
        ];

        $this->postJson($this->endpoint, $payload);

        // Verify the digital product's actual price in database hasn't changed
        $dp->refresh();
        $this->assertEquals($originalPrice, $dp->selling_price);
    }

    public function test_preview_includes_all_required_fields(): void
    {
        $dp = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Complete Product',
            'brand' => 'Complete',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'Complete',
                ],
            ],
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(200);
        $preview = $response->json('data');

        $this->assertCount(1, $preview);
        $item = $preview[0];
        $this->assertArrayHasKey('digital_product_id', $item);
        $this->assertArrayHasKey('digital_product_name', $item);
        $this->assertArrayHasKey('face_value', $item);
        $this->assertArrayHasKey('current_selling_price', $item);
        $this->assertArrayHasKey('new_selling_price', $item);
    }
}
