<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductPriorityControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_update_digital_products_priority(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProducts = DigitalProduct::factory()->count(3)->create();

        // Assign digital products to product
        $product->digitalProducts()->attach($digitalProducts->pluck('id'));

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => $digitalProducts[0]->id,
                    'priority_order' => 3,
                ],
                [
                    'digital_product_id' => $digitalProducts[1]->id,
                    'priority_order' => 1,
                ],
                [
                    'digital_product_id' => $digitalProducts[2]->id,
                    'priority_order' => 2,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonPath('message', 'Digital product priorities updated successfully.');

        // Verify priorities are updated in the database
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[0]->id,
            'priority' => 3,
        ]);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[1]->id,
            'priority' => 1,
        ]);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[2]->id,
            'priority' => 2,
        ]);
    }

    public function test_update_priority_partial_digital_products(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProducts = DigitalProduct::factory()->count(3)->create();

        // Assign all digital products
        $product->digitalProducts()->attach($digitalProducts->pluck('id'));

        // Update priority for only 2 of 3 products
        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => $digitalProducts[0]->id,
                    'priority_order' => 1,
                ],
                [
                    'digital_product_id' => $digitalProducts[1]->id,
                    'priority_order' => 2,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(200);

        // Verify updated priorities
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[0]->id,
            'priority' => 1,
        ]);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'digital_product_id' => $digitalProducts[1]->id,
            'priority' => 2,
        ]);
    }

    public function test_update_priority_with_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id);

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => 99999,
                    'priority_order' => 1,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_products.0.digital_product_id']);
    }

    public function test_update_priority_requires_digital_products_array(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_products']);
    }

    public function test_update_priority_requires_valid_priority_order(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id);

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'priority_order' => 'invalid',
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_products.0.priority_order']);
    }

    public function test_update_priority_priority_order_must_be_positive(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $product->digitalProducts()->attach($digitalProduct->id);

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'priority_order' => 0,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['digital_products.0.priority_order']);
    }

    public function test_update_priority_for_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => 1,
                    'priority_order' => 1,
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/products/999999/digital-products/priority',
            $priorityData
        );

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_update_priority(): void
    {
        $product = Product::factory()->create();

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => 1,
                    'priority_order' => 1,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(401);
    }

    public function test_response_includes_updated_product_data(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Test Product']);
        $digitalProducts = DigitalProduct::factory()->count(2)->create();

        $product->digitalProducts()->attach($digitalProducts->pluck('id'));

        $priorityData = [
            'digital_products' => [
                [
                    'digital_product_id' => $digitalProducts[0]->id,
                    'priority_order' => 1,
                ],
                [
                    'digital_product_id' => $digitalProducts[1]->id,
                    'priority_order' => 2,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/products/{$product->id}/digital-products/priority",
            $priorityData
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'digital_products' => [
                        '*' => [
                            'id',
                            'name',
                            'pivot' => [
                                'priority',
                            ],
                        ],
                    ],
                ],
                'message',
            ])
            ->assertJsonPath('data.name', 'Test Product');
    }
}
