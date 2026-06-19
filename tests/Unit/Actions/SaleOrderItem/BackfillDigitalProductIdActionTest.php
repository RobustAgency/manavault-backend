<?php

namespace Tests\Unit\Actions\SaleOrderItem;

use Tests\TestCase;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\Product\FulfillmentMode;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\SaleOrderItem\BackfillDigitalProductIdAction;

class BackfillDigitalProductIdActionTest extends TestCase
{
    use RefreshDatabase;

    private BackfillDigitalProductIdAction $action;

    private SaleOrder $saleOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(BackfillDigitalProductIdAction::class);
        $this->saleOrder = SaleOrder::factory()->create();
    }

    public function test_it_backfills_from_historical_allocation_preferring_most_allocated(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);

        $allocatedMore = DigitalProduct::factory()->create();
        $allocatedLess = DigitalProduct::factory()->create();

        // Resolution fallback would pick something else; allocation must win.
        $product->digitalProducts()->attach([$allocatedLess->id, $allocatedMore->id]);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => null,
        ]);

        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->forDigitalProduct($allocatedLess)->create();
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->forDigitalProduct($allocatedMore)->create();
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->forDigitalProduct($allocatedMore)->create();

        $this->action->execute();

        $this->assertSame($allocatedMore->id, $item->fresh()->digital_product_id);
    }

    public function test_it_ignores_allocation_rows_with_null_digital_product(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);
        $allocated = DigitalProduct::factory()->create();
        $product->digitalProducts()->attach($allocated->id);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => null,
        ]);

        // Deleted products leave null digital_product_id rows (the majority); must be ignored.
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->create(['digital_product_id' => null]);
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->create(['digital_product_id' => null]);
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->forDigitalProduct($allocated)->create();

        $this->action->execute();

        $this->assertSame($allocated->id, $item->fresh()->digital_product_id);
    }

    public function test_it_falls_back_to_lowest_cost_for_price_mode_when_no_allocation(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);

        $cheaper = DigitalProduct::factory()->create(['cost_price' => 5]);
        $pricier = DigitalProduct::factory()->create(['cost_price' => 50]);
        $product->digitalProducts()->attach([$pricier->id, $cheaper->id]);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => null,
        ]);

        $this->action->execute();

        $this->assertSame($cheaper->id, $item->fresh()->digital_product_id);
    }

    public function test_it_falls_back_to_highest_priority_for_manual_mode_when_no_allocation(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);

        $lowPriority = DigitalProduct::factory()->create(['cost_price' => 5]);
        $highPriority = DigitalProduct::factory()->create(['cost_price' => 50]);
        $product->digitalProducts()->attach($lowPriority->id, ['priority' => 1]);
        $product->digitalProducts()->attach($highPriority->id, ['priority' => 10]);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => null,
        ]);

        $this->action->execute();

        // orderByPivot('priority') ascending picks the lowest priority value first.
        $this->assertSame($lowPriority->id, $item->fresh()->digital_product_id);
    }

    public function test_it_skips_when_no_source_resolves(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => null,
        ]);

        $this->action->execute();

        $this->assertNull($item->fresh()->digital_product_id);
    }

    public function test_it_leaves_already_populated_items_untouched(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);

        $existing = DigitalProduct::factory()->create();
        $other = DigitalProduct::factory()->create();
        $product->digitalProducts()->attach($other->id);

        $item = SaleOrderItem::factory()->forSaleOrder($this->saleOrder)->forProduct($product)->create([
            'digital_product_id' => $existing->id,
        ]);

        $this->action->execute();

        $this->assertSame($existing->id, $item->fresh()->digital_product_id);
    }
}
