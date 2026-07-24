<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Bus;
use App\Constants\ActivityEvents;
use Illuminate\Support\Facades\Config;
use Spatie\WebhookServer\CallWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncProductsOnDigitalProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhook-server.webhook_url', 'https://example.test/webhook-product-sync');
        Config::set('webhook-server.webhook_secret', 'test-secret');
    }

    public function test_dispatches_sync_webhook_when_assigned_digital_product_becomes_inactive(): void
    {
        Bus::fake();

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create(['is_active' => true]);

        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $digitalProduct->update(['is_active' => false]);

        // Touching the product funnels through the single Product `updated` sync.
        Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($product) {
            return $job->payload['event'] === ActivityEvents::PRODUCT_UPDATED
                && $job->payload['product_ids'] === [$product->id];
        });
    }

    public function test_dispatches_sync_webhook_for_all_products_linked_to_inactive_digital_product(): void
    {
        Bus::fake();

        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create(['is_active' => true]);

        $productOne->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);
        $productTwo->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $digitalProduct->update(['is_active' => false]);

        // Each affected product is touched and syncs individually.
        foreach ([$productOne, $productTwo] as $product) {
            Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($product) {
                return $job->payload['event'] === ActivityEvents::PRODUCT_UPDATED
                    && $job->payload['product_ids'] === [$product->id];
            });
        }
    }

    public function test_does_not_dispatch_sync_webhook_when_digital_product_has_no_assigned_products(): void
    {
        Bus::fake();

        $digitalProduct = DigitalProduct::factory()->create(['is_active' => true]);

        $digitalProduct->update(['is_active' => false]);

        Bus::assertNotDispatched(CallWebhookJob::class);
    }
}
