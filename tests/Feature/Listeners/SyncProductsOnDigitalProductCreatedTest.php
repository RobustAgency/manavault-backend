<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Spatie\WebhookServer\CallWebhookJob;
use App\Events\DigitalProductCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Listeners\SyncProductsOnDigitalProductCreated;

class SyncProductsOnDigitalProductCreatedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhook-server.webhook_url', 'https://example.test/webhook-product-sync');
        Config::set('webhook-server.webhook_secret', 'test-secret');
    }

    public function test_dispatches_sync_webhook_on_create_event_when_digital_product_is_linked_to_products(): void
    {
        Bus::fake();

        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        event(new DigitalProductCreated($digitalProduct));

        Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($product) {
            return $job->payload['event'] === SyncProductsOnDigitalProductCreated::EVENT_NAME
                && $job->payload['product_ids'] === [$product->id];
        });
    }

    public function test_does_not_dispatch_sync_webhook_when_newly_created_digital_product_has_no_assigned_products(): void
    {
        Bus::fake();

        $digitalProduct = DigitalProduct::factory()->create();

        Bus::assertNotDispatched(CallWebhookJob::class);
    }
}
