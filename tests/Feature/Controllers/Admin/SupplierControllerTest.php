<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_admin_get_paginated_suppliers(): void
    {
        $suppliers = Supplier::factory()->count(5)->create();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $response = $this->getJson('/api/admin/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'contact_email',
                            'contact_phone',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
                'message',
            ]);
    }

    public function test_admin_create_supplier(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $supplierData = [
            'name' => $this->faker->company,
            'type' => $this->faker->randomElement(['internal', 'external']),
            'contact_email' => $this->faker->unique()->safeEmail,
            'contact_phone' => $this->faker->phoneNumber,
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];

        $response = $this->postJson('/api/admin/suppliers', $supplierData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'contact_email',
                    'contact_phone',
                    'status',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => $supplierData['name'],
            'contact_email' => $supplierData['contact_email'],
        ]);
    }

    public function test_admin_update_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $updateData = [
            'name' => $this->faker->company,
            'type' => $this->faker->randomElement(['internal', 'external']),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];

        $response = $this->postJson("/api/admin/suppliers/{$supplier->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data',
                'message',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => $updateData['name'],
            'type' => $updateData['type'],
        ]);
    }

    public function test_admin_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->deleteJson("/api/admin/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data',
                'message',
            ]);

        $this->assertDatabaseMissing('suppliers', [
            'id' => $supplier->id,
        ]);
    }
}
