<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierKpiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_supplier_kpis()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
        ]);
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
        PurchaseOrderItem::factory()->create([
            'supplier_id' => $supplier->id,
            'quantity' => 10,
            'subtotal' => 100.0,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/suppliers/kpis');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'data' => [
                        ['supplier_id', 'supplier_name', 'total_purchase_orders', 'completed_purchase_orders', 'processing_purchase_orders', 'total_quantity_ordered', 'total_amount_spent', 'average_order_value', 'completion_rate'],
                    ],
                    'current_page', 'last_page', 'per_page', 'total',
                ],
                'message',
            ]);

        $kpi = $response->json('data.data.0');
        $this->assertEquals($supplier->id, $kpi['supplier_id']);
        $this->assertEquals('Test Supplier', $kpi['supplier_name']);
        $this->assertEquals(2, $kpi['total_purchase_orders']);
        $this->assertEquals(1, $kpi['completed_purchase_orders']);
        $this->assertEquals(1, $kpi['processing_purchase_orders']);
        $this->assertEquals(10, $kpi['total_quantity_ordered']);
        $this->assertEquals(100.0, $kpi['total_amount_spent']);
        $this->assertEquals(50.0, $kpi['average_order_value']);
        $this->assertEquals(50.0, $kpi['completion_rate']);
    }
}
