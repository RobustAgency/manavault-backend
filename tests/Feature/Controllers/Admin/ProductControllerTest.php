<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_admin_update_product(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Product Name',
            'description' => 'Updated product description',
            'price' => 200.00,
        ];

        $response = $this->postJson("/api/admin/products/{$product->id}", $updateData);

        $response->assertStatus(200)
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

        $this->assertEquals($response->json('message'), 'Product updated successfully.');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $updateData['name'],
            'description' => $updateData['description'],
            'price' => $updateData['price'],
        ]);
    }
}
