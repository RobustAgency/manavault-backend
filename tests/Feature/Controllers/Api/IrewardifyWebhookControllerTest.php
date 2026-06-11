<?php

namespace Tests\Feature\Controllers\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IrewardifyWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_ordered_event(): void
    {
        $response = $this->postJson('/api/webhooks/irewardify', [
            'event' => 'Order',
            'message' => 'Order placed successfully',
            'orderId' => 'TEST0001',
            'status' => 'Ordered',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Webhook received.',
            'error' => false,
        ]);
    }

    public function test_accepts_delivered_event(): void
    {
        $response = $this->postJson('/api/webhooks/irewardify', [
            'event' => 'Order',
            'message' => 'Order delivered successfully',
            'orderId' => 'TEST0001',
            'status' => 'Delivered',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Webhook received.',
            'error' => false,
        ]);
    }

    public function test_accepts_failed_event(): void
    {
        $response = $this->postJson('/api/webhooks/irewardify', [
            'event' => 'Order',
            'message' => 'Order failed',
            'orderId' => 'TEST0001',
            'status' => 'Failed',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Webhook received.',
            'error' => false,
        ]);
    }

    public function test_requires_order_id(): void
    {
        $response = $this->postJson('/api/webhooks/irewardify', [
            'event' => 'Order',
            'message' => 'Order delivered successfully',
            'status' => 'Delivered',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['orderId']);
    }

    public function test_requires_valid_status(): void
    {
        $response = $this->postJson('/api/webhooks/irewardify', [
            'event' => 'Order',
            'message' => 'Order delivered successfully',
            'orderId' => 'TEST0001',
            'status' => 'Unknown',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }
}
