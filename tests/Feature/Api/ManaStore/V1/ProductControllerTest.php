<?php

namespace Tests\Feature\Api\ManaStore\V1;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $endpoint = '/api/v1/products';

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = config('services.manastore.api_key');
    }

    /**
     * Test: Get all products without authentication should fail
     */
    public function test_get_products_without_authentication_fails(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(401);
    }

    /**
     * Test: Get all products with valid authentication returns all products
     */
    public function test_get_products_with_valid_authentication_returns_all_products(): void
    {
        // Create test data
        $brand = Brand::factory()->create();
        Product::factory()->count(5)->create(['brand_id' => $brand->id]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ])
            ->assertJsonStructure([
                'error',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'brand_id',
                            'face_value',
                            'selling_price',
                            'status',
                        ],
                    ],
                    'total',
                    'per_page',
                    'current_page',
                ],
                'message',
            ]);

        $this->assertCount(5, $response->json('data.data'));
    }

    /**
     * Test: Get products returns correct data structure
     */
    public function test_get_products_returns_correct_data_structure(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sony']);
        Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'PlayStation 5',
            'face_value' => 100.00,
            'selling_price' => 110.00,
            'status' => 1,
        ]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $product = $response->json('data.data.0');

        $this->assertNotNull($product['id']);
        $this->assertEquals('PlayStation 5', $product['name']);
        $this->assertEquals(100.00, (float) $product['face_value']);
        $this->assertEquals(110.00, (float) $product['selling_price']);
        $this->assertEquals(1, $product['status']);
    }

    /**
     * Test: Get products returns empty array when no products exist
     */
    public function test_get_products_returns_empty_array_when_no_products_exist(): void
    {
        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ]);

        $this->assertEmpty($response->json('data.data'));
    }

    /**
     * Test: Get products with multiple brands
     */
    public function test_get_products_with_multiple_brands(): void
    {
        $brand1 = Brand::factory()->create(['name' => 'Sony']);
        $brand2 = Brand::factory()->create(['name' => 'Microsoft']);
        $brand3 = Brand::factory()->create(['name' => 'Nintendo']);

        Product::factory()->count(2)->create(['brand_id' => $brand1->id, 'name' => 'PlayStation']);
        Product::factory()->count(3)->create(['brand_id' => $brand2->id, 'name' => 'Xbox']);
        Product::factory()->count(1)->create(['brand_id' => $brand3->id, 'name' => 'Switch']);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data.data'));
    }

    /**
     * Test: Get products includes all required fields
     */
    public function test_get_products_includes_all_required_fields(): void
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('brand_id', $data);
        $this->assertArrayHasKey('face_value', $data);
        $this->assertArrayHasKey('selling_price', $data);
        $this->assertArrayHasKey('status', $data);
    }

    /**
     * Test: Get products response has correct JSON structure
     */
    public function test_get_products_response_has_correct_json_structure(): void
    {
        Brand::factory()->create();
        Product::factory()->create();

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data',
                'message',
            ]);

        $this->assertIsBool($response->json('error'));
        $this->assertIsArray($response->json('data'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test: Get products response error flag is false
     */
    public function test_get_products_response_error_flag_is_false(): void
    {
        Brand::factory()->create();
        Product::factory()->create();

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertFalse($response->json('error'));
    }

    /**
     * Test: Get products response has success message
     */
    public function test_get_products_response_has_success_message(): void
    {
        Brand::factory()->create();
        Product::factory()->create();

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertEquals('Products retrieved successfully.', $response->json('message'));
    }

    /**
     * Test: Get products with various product statuses
     */
    public function test_get_products_with_various_product_statuses(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->count(3)->create(['brand_id' => $brand->id, 'status' => 1]);
        Product::factory()->count(2)->create(['brand_id' => $brand->id, 'status' => 0]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data.data'));

        $activeProducts = collect($response->json('data.data'))->where('status', 1)->count();
        $this->assertEquals(3, $activeProducts);
    }

    /**
     * Test: Get products with different price points
     */
    public function test_get_products_with_different_price_points(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 10.00,
            'selling_price' => 12.00,
        ]);
        Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 100.00,
            'selling_price' => 120.00,
        ]);
        Product::factory()->create([
            'brand_id' => $brand->id,
            'face_value' => 1000.00,
            'selling_price' => 1200.00,
        ]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));

        $prices = collect($response->json('data.data'))->pluck('face_value')->sort()->toArray();
        $this->assertEquals([10.00, 100.00, 1000.00], array_map('floatval', $prices));
    }

    /**
     * Test: Get products with invalid token fails
     */
    public function test_get_products_with_invalid_token_fails(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token')
            ->getJson($this->endpoint);

        $response->assertStatus(401);
    }

    /**
     * Test: Get products without Authorization header fails
     */
    public function test_get_products_without_authorization_header_fails(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(401);
    }

    /**
     * Test: Get products with malformed Authorization header fails
     */
    public function test_get_products_with_malformed_authorization_header_fails(): void
    {
        $response = $this->withHeader('Authorization', 'InvalidFormat token123')
            ->getJson($this->endpoint);

        $response->assertStatus(401);
    }

    /**
     * Test: Get products returns correct content-type
     */
    public function test_get_products_returns_correct_content_type(): void
    {
        Brand::factory()->create();
        Product::factory()->create();

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    /**
     * Test: Get products response is valid JSON
     */
    public function test_get_products_response_is_valid_json(): void
    {
        Brand::factory()->create();
        Product::factory()->create();

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /**
     * Test: Get products with pagination-like data
     */
    public function test_get_products_with_large_dataset(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->count(100)->create(['brand_id' => $brand->id]);

        $response = $this->withHeader('x-api-key', $this->token)
            ->getJson($this->endpoint);

        $response->assertStatus(200);
        // Default pagination is 15 items per page, so we expect 15 items in the first request
        $data = collect($response->json('data.data'));
        $this->assertLessThanOrEqual(15, $data->count());
        $this->assertGreaterThan(0, $data->count());
        // Check total count in pagination metadata
        $this->assertEquals(100, $response->json('data.total'));
    }
}
