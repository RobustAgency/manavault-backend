<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Spatie\WebhookServer\CallWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Listeners\SyncProductsOnDigitalProductDeleting;

class SyncProductsOnDigitalProductDeletingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhook-server.webhook_url', 'https://example.test/webhook-product-sync');
        Config::set('webhook-server.webhook_secret', 'test-secret');
    }

    public function test_dispatches_sync_webhook_for_products_linked_to_deleted_digital_product(): void
    {
        Bus::fake();

        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $productOne->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);
        $productTwo->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $digitalProduct->delete();

        $this->assertDatabaseMissing('digital_products', ['id' => $digitalProduct->id]);
        $this->assertDatabaseMissing('product_supplier', ['digital_product_id' => $digitalProduct->id]);

        Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($productOne, $productTwo) {
            return $job->payload['event'] === SyncProductsOnDigitalProductDeleting::EVENT_NAME
                && $job->payload['product_ids'] === [$productOne->id, $productTwo->id];
        });
    }

    public function test_does_not_dispatch_sync_webhook_when_deleted_digital_product_has_no_assigned_products(): void
    {
        Bus::fake();

        $digitalProduct = DigitalProduct::factory()->create();

        $digitalProduct->delete();

        Bus::assertNotDispatched(CallWebhookJob::class);
    }
}
