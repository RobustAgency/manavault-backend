<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Repositories\SupplierKpiRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierKpiRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_filtered_suppliers_kpis_returns_correct_kpis()
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

        // Create purchase orders for supplier
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
        ]);
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        // Create items for supplier
        PurchaseOrderItem::factory()->create([
            'supplier_id' => $supplier->id,
            'quantity' => 10,
            'subtotal' => 100.0,
        ]);
        PurchaseOrderItem::factory()->create([
            'supplier_id' => $supplier->id,
            'quantity' => 5,
            'subtotal' => 50.0,
        ]);

        // Create purchase orders and items for other supplier
        PurchaseOrderSupplier::factory()->create([
            'supplier_id' => $otherSupplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
        ]);
        PurchaseOrderItem::factory()->create([
            'supplier_id' => $otherSupplier->id,
            'quantity' => 3,
            'subtotal' => 30.0,
        ]);

        $repo = new SupplierKpiRepository;
        $result = $repo->getFilteredSuppliersKpis(['perPage' => 10]);
        $data = $result->getCollection()->keyBy('supplier_id');

        $supplierKpi = $data[$supplier->id];
        $this->assertEquals($supplier->id, $supplierKpi['supplier_id']);
        $this->assertEquals('Test Supplier', $supplierKpi['supplier_name']);
        $this->assertEquals(3, $supplierKpi['total_purchase_orders']);
        $this->assertEquals(1, $supplierKpi['completed_purchase_orders']);
        $this->assertEquals(2, $supplierKpi['processing_purchase_orders']);
        $this->assertEquals(15, $supplierKpi['total_quantity_ordered']);
        $this->assertEquals(150.0, $supplierKpi['total_amount_spent']);
        $this->assertEquals(round(150.0 / 3, 2), $supplierKpi['average_order_value']);
        $this->assertEquals(round((1 / 3) * 100, 2), $supplierKpi['completion_rate']);

        $otherKpi = $data[$otherSupplier->id];
        $this->assertEquals($otherSupplier->id, $otherKpi['supplier_id']);
        $this->assertEquals('Other Supplier', $otherKpi['supplier_name']);
        $this->assertEquals(1, $otherKpi['total_purchase_orders']);
        $this->assertEquals(1, $otherKpi['completed_purchase_orders']);
        $this->assertEquals(0, $otherKpi['processing_purchase_orders']);
        $this->assertEquals(3, $otherKpi['total_quantity_ordered']);
        $this->assertEquals(30.0, $otherKpi['total_amount_spent']);
        $this->assertEquals(30.0, $otherKpi['average_order_value']);
        $this->assertEquals(100.0, $otherKpi['completion_rate']);
    }
}
