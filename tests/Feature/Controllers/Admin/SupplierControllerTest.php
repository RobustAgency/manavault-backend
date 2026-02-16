<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Enums\SupplierType;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_admin_get_paginated_suppliers(): void
    {
        $suppliers = Supplier::factory()->count(5)->create();
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/suppliers');

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

    public function test_admin_can_create_only_internal_supplier(): void
    {
        $this->actingAs($this->admin);
        $supplierData = [
            'name' => $this->faker->company,
            'contact_email' => $this->faker->unique()->safeEmail,
            'contact_phone' => $this->faker->phoneNumber,
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];

        $response = $this->postJson('/api/suppliers', $supplierData);

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
            'type' => SupplierType::INTERNAL->value,
        ]);
    }

    public function test_admin_update_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->admin);
        $updateData = [
            'name' => $this->faker->company,
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];

        $response = $this->postJson("/api/suppliers/{$supplier->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data',
                'message',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => $updateData['name'],
            'type' => SupplierType::INTERNAL->value,
        ]);
    }

    public function test_admin_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

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
