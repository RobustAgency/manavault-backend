<?php

namespace Tests\Feature\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use Tests\TestCase;

class ProductBatchControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_admin_create_product_batch(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $data = [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'supplier_id' => \App\Models\Supplier::factory()->create()->id,
            'purchase_price' => $this->faker->randomFloat(2, 1, 100),
            'quantity' => $this->faker->numberBetween(1, 100),
        ];

        $response = $this->postJson('/api/admin/products/batch', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'description',
                    'price',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);

        $this->assertEquals($response->json('message'), 'Product batch created successfully.');

        $this->assertDatabaseHas('products', [
            'name' => $data['name'],
            'sku' => $data['sku'],
        ]);

        $this->assertDatabaseHas('product_batches', [
            'supplier_id' => $data['supplier_id'],
            'purchase_price' => $data['purchase_price'],
            'quantity' => $data['quantity'],
        ]);
    }
}
