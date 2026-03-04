<?php

namespace Tests\Unit\Factory\Gift2Games;

use Tests\TestCase;
use App\Clients\Gift2GamesClient;
use App\Factory\Gift2Games\ClientFactory;

class ClientFactoryTest extends TestCase
{
    private ClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ClientFactory;
    }

    public function test_get_config_prefix_returns_correct_prefix_for_known_slug(): void
    {
        $slug = 'gift2games';
        $expectedPrefix = 'services.gift2games';

        $this->assertSame($expectedPrefix, $this->factory->getConfigPrefix($slug));
    }

    public function test_get_config_prefix_throws_for_unknown_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: unknown-slug');

        $this->factory->getConfigPrefix('unknown-slug');
    }

    public function test_get_config_prefix_throws_for_empty_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: ');

        $this->factory->getConfigPrefix('');
    }

    public function test_make_client_returns_gift2games_client_instance(): void
    {
        $client = $this->factory->makeClient('gift2games');

        $this->assertInstanceOf(Gift2GamesClient::class, $client);
    }

    public function test_make_client_returns_distinct_instances_for_different_slugs(): void
    {
        $clientUsd = $this->factory->makeClient('gift2games');
        $clientEur = $this->factory->makeClient('gift-2-games-eur');

        $this->assertNotSame($clientUsd, $clientEur);
    }

    public function test_make_client_throws_for_unknown_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: bad-slug');

        $this->factory->makeClient('bad-slug');
    }
}
