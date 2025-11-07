<?php

namespace App\Actions\Gift2Games;

use App\Clients\Gift2Games\Products;

class GetProducts
{

    public function __construct(private Products $productsClient) {}

    public function execute(int $offset, int $limit, array $queryParams = [],): array
    {
        return $this->productsClient->fetchList($offset, $limit, $queryParams);
    }
}
