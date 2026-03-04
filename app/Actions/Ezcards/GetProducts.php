<?php

namespace App\Actions\Ezcards;

use App\Clients\EzcardsClient;

class GetProducts
{
    public function __construct(private EzcardsClient $client) {}

    public function execute(int $limit, int $page): array
    {
        $queryParams = [
            'limit' => $limit,
            'page' => $page,
        ];

        return $this->client->getProducts($queryParams);
    }
}
