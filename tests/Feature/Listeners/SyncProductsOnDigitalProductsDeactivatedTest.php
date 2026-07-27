<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Spatie\WebhookServer\CallWebhookJob;
use App\Events\DigitalProductsDeactivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Listeners\SyncProductsOnDigitalProductsDeactivated;

class SyncProductsOnDigitalProductsDeactivatedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhook-server.webhook_url', 'https://example.test/webhook-product-sync');
        Config::set('webhook-server.webhook_secret', 'test-secret');
    }

    public function test_dispatches_sync_webhook_for_products_linked_to_deactivated_digital_products(): void
    {
        Bus::fake();

        $productOne = Product::factory()->create();
        $productTwo = Product::factory()->create();
        $digitalProductOne = DigitalProduct::factory()->create(['is_active' => true]);
        $digitalProductTwo = DigitalProduct::factory()->create(['is_active' => true]);

        $productOne->digitalProducts()->attach($digitalProductOne->id, ['priority' => 1]);
        $productTwo->digitalProducts()->attach($digitalProductTwo->id, ['priority' => 1]);

        // Simulate the mass-update path: rows already flipped to inactive before the event fires.
        DigitalProduct::whereIn('id', [$digitalProductOne->id, $digitalProductTwo->id])
            ->update(['is_active' => false]);

        event(new DigitalProductsDeactivated([$digitalProductOne->id, $digitalProductTwo->id]));

        Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($productOne, $productTwo) {
            return $job->payload['event'] === SyncProductsOnDigitalProductsDeactivated::EVENT_NAME
                && $job->payload['product_ids'] === [$productOne->id, $productTwo->id];
        });
    }

    public function test_does_not_dispatch_sync_webhook_when_no_products_are_linked(): void
    {
        Bus::fake();

        $digitalProduct = DigitalProduct::factory()->create(['is_active' => false]);

        event(new DigitalProductsDeactivated([$digitalProduct->id]));

        Bus::assertNotDispatched(CallWebhookJob::class);
    }
}
