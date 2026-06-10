<?php

namespace Tests\Unit\Actions\PurchaseOrder;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Support\Facades\Queue;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\PurchaseOrder\PlacePendingPurchaseOrders;

class PlacePendingPurchaseOrdersTest extends TestCase
{
    use RefreshDatabase;

    private PlacePendingPurchaseOrders $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(PlacePendingPurchaseOrders::class);
    }

    public function test_dispatches_job_for_integrated_supplier_without_transaction_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_skips_supplier_not_in_integration(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'unintegrated_supplier']);
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertNotPushed(PlaceExternalPurchaseOrderJob::class);
    }

    public function test_skips_supplier_that_already_has_transaction_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => 'TXN-already-placed',
        ]);

        $this->action->execute();

        Queue::assertNotPushed(PlaceExternalPurchaseOrderJob::class);
    }

    public function test_skips_supplier_with_non_processing_status(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertNotPushed(PlaceExternalPurchaseOrderJob::class);
    }

    public function test_only_dispatches_for_integrated_suppliers_when_mixed(): void
    {
        Queue::fake();

        $ezCardsSupplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $otherSupplier = Supplier::factory()->create(['slug' => 'unintegrated_supplier']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $ezCardsSupplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $otherSupplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_dispatches_job_for_gift2games_usd_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_dispatches_job_for_gift2games_eur_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'gift-2-games-eur']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'EUR']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_dispatches_job_for_gift2games_gbp_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'gift-2-games-gbp']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'GBP']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_dispatches_jobs_for_all_gift2games_wallets_in_one_run(): void
    {
        Queue::fake();

        $usdSupplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $eurSupplier = Supplier::factory()->create(['slug' => 'gift-2-games-eur']);
        $gbpSupplier = Supplier::factory()->create(['slug' => 'gift-2-games-gbp']);

        foreach ([[$usdSupplier, 'USD'], [$eurSupplier, 'EUR'], [$gbpSupplier, 'GBP']] as [$supplier, $currency]) {
            $purchaseOrder = PurchaseOrder::factory()->create(['currency' => $currency]);

            PurchaseOrderSupplier::factory()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
                'transaction_id' => null,
            ]);
        }

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 3);
    }

    public function test_dispatches_one_job_per_eligible_integrated_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD']);

        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->action->execute();

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }
}
