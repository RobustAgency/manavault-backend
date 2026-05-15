<?php

namespace Tests\Unit\Actions\Giftery;

use Tests\TestCase;
use App\Clients\Giftery\Client;
use App\Actions\Giftery\PlaceOrderAction;

class PlaceOrderActionTest extends TestCase
{
    public function test_execute_succeeds_with_codes_in_response(): void
    {
        $mockClient = $this->mock(Client::class);

        $reserveResponse = [
            'statusCode' => 0,
            'data' => [
                'transactionUUID' => 'test-uuid-123',
                'status' => 'reserved',
                'amount' => 100.00,
            ],
        ];

        $confirmResponse = [
            'uuid' => 'test-uuid-123',
            'status' => 'confirmed',
            'codes' => [
                [
                    'code' => 'TEST-CODE-001',
                    'pin' => '1234',
                    'serial' => 'SN-12345',
                    'expiryDate' => '2027-12-31',
                ],
            ],
        ];

        $mockClient
            ->shouldReceive('reserveOrder')
            ->once()
            ->with([
                'itemId' => 12345,
                'fields' => [['key' => 'email', 'value' => 'test@example.com']],
            ])
            ->andReturn($reserveResponse);

        $mockClient
            ->shouldReceive('confirmOrder')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($confirmResponse);

        $action = new PlaceOrderAction($mockClient);

        $result = $action->execute([
            'itemId' => 12345,
            'fields' => [['key' => 'email', 'value' => 'test@example.com']],
        ]);

        $this->assertEquals('test-uuid-123', $result['transactionUUID']);
        $this->assertEquals('confirmed', $result['confirmResponse']['status']);
        $this->assertNotEmpty($result['confirmResponse']['codes']);
    }

    public function test_execute_succeeds_without_codes_in_response(): void
    {
        $mockClient = $this->mock(Client::class);

        $reserveResponse = [
            'statusCode' => 0,
            'data' => [
                'transactionUUID' => 'test-uuid-456',
                'status' => 'reserved',
            ],
        ];

        $confirmResponse = [
            'uuid' => 'test-uuid-456',
            'status' => 'confirmed',
            'codes' => [],
        ];

        $mockClient
            ->shouldReceive('reserveOrder')
            ->once()
            ->andReturn($reserveResponse);

        $mockClient
            ->shouldReceive('confirmOrder')
            ->once()
            ->with('test-uuid-456')
            ->andReturn($confirmResponse);

        $action = new PlaceOrderAction($mockClient);

        $result = $action->execute([
            'itemId' => 12345,
            'fields' => [['key' => 'email', 'value' => 'test@example.com']],
        ]);

        $this->assertEquals('test-uuid-456', $result['transactionUUID']);
        $this->assertEmpty($result['confirmResponse']['codes']);
    }

    public function test_execute_throws_on_reserve_failure(): void
    {
        $mockClient = $this->mock(Client::class);

        $reserveResponse = [
            'statusCode' => -1,
            'message' => 'Reserve failed',
        ];

        $mockClient
            ->shouldReceive('reserveOrder')
            ->once()
            ->andReturn($reserveResponse);

        $action = new PlaceOrderAction($mockClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Giftery reserve failed');

        $action->execute([
            'itemId' => 12345,
            'fields' => [['key' => 'email', 'value' => 'test@example.com']],
        ]);
    }

    public function test_execute_throws_on_missing_uuid(): void
    {
        $mockClient = $this->mock(Client::class);

        $reserveResponse = [
            'statusCode' => 0,
            'data' => ['status' => 'reserved'],
        ];

        $mockClient
            ->shouldReceive('reserveOrder')
            ->once()
            ->andReturn($reserveResponse);

        $action = new PlaceOrderAction($mockClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing transaction UUID');

        $action->execute([
            'itemId' => 12345,
            'fields' => [['key' => 'email', 'value' => 'test@example.com']],
        ]);
    }
}
