<?php

namespace Tests\Unit\Clients;

use Tests\TestCase;
use Illuminate\Support\Sleep;
use App\Clients\Irewardify\Client;
use Illuminate\Support\Facades\Http;

class IrewardifyClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.irewardify.url' => 'https://irewardify.test']);

        cache()->put('irewardify_access_token', 'fake-token', 3600);

        $this->client = new Client;
    }

    private function rateLimitResponse(): array
    {
        return ['success' => false, 'message' => 'Too many requests, please try again after a minute.'];
    }

    private function productDetailsResponse(): array
    {
        return ['data' => ['variants' => [['sku' => 'SKU-1']]]];
    }

    public function test_get_product_details_retries_after_rate_limit_using_retry_after_header(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::sequence()
                ->push($this->rateLimitResponse(), 429, ['Retry-After' => '2'])
                ->push($this->productDetailsResponse(), 200),
        ]);

        $result = $this->client->getProductDetails('PROD-1');

        $this->assertSame([['sku' => 'SKU-1']], $result['data']['variants']);

        Http::assertSentCount(2);

        Sleep::assertSequence([Sleep::for(2000)->milliseconds()]);
    }

    public function test_get_product_details_waits_a_minute_when_rate_limited_without_retry_after_header(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::sequence()
                ->push($this->rateLimitResponse(), 429)
                ->push($this->productDetailsResponse(), 200),
        ]);

        $result = $this->client->getProductDetails('PROD-1');

        $this->assertSame([['sku' => 'SKU-1']], $result['data']['variants']);

        Sleep::assertSequence([Sleep::for(61000)->milliseconds()]);
    }

    public function test_get_product_details_throws_after_exhausting_retries_on_persistent_rate_limit(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::response($this->rateLimitResponse(), 429),
        ]);

        try {
            $this->client->getProductDetails('PROD-1');

            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to fetch product details from Irewardify', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_get_product_details_does_not_retry_on_client_errors(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::response(['message' => 'Not found'], 404),
        ]);

        try {
            $this->client->getProductDetails('PROD-1');

            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to fetch product details from Irewardify', $e->getMessage());
        }

        Http::assertSentCount(1);

        Sleep::assertNeverSlept();
    }
}
