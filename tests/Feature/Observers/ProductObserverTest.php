<?php

namespace Tests\Feature\Observers;

use Tests\TestCase;
use App\Models\Product;
use App\Constants\ActivityEvents;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Spatie\WebhookServer\CallWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhook-server.webhook_url', 'https://example.test/webhook-product-sync');
        Config::set('webhook-server.webhook_secret', 'test-secret');
    }

    public function test_dispatches_deletion_webhook_with_just_the_id_after_the_product_is_gone(): void
    {
        Bus::fake();

        $product = Product::factory()->create();
        $productId = $product->id;

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        Bus::assertDispatched(CallWebhookJob::class, function (CallWebhookJob $job) use ($productId) {
            return $job->payload['event'] === ActivityEvents::PRODUCT_DELETED
                && $job->payload['data'] === ['id' => $productId];
        });
    }
}
