<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierKpiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_supplier_kpis(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create();

        $purchaseOrders = PurchaseOrder::factory()->count(3)->create();

        PurchaseOrderSupplier::query()->create([
            'purchase_order_id' => $purchaseOrders[0]->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
        ]);

        PurchaseOrderSupplier::query()->create([
            'purchase_order_id' => $purchaseOrders[1]->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
        ]);

        PurchaseOrderSupplier::query()->create([
            'purchase_order_id' => $purchaseOrders[2]->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrders[0]->id,
            'supplier_id' => $supplier->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 5,
            'unit_cost' => 10,
            'subtotal' => 50,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrders[1]->id,
            'supplier_id' => $supplier->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 10,
            'unit_cost' => 12,
            'subtotal' => 120,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/api/admin/suppliers/{$supplier->id}/kpis");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'supplier_id' => $supplier->id,
                    'total_purchase_orders' => 3,
                    'completed_purchase_orders' => 2,
                    'processing_purchase_orders' => 1,
                    'total_quantity_ordered' => 15,
                    'total_amount_spent' => 170.00,
                    'average_order_value' => 56.67,
                    'completion_rate' => 66.67,
                ],
                'message' => 'Supplier KPIs retrieved successfully.',
            ]);
    }
}
