<?php

namespace App\Actions\Ezcards;

use App\Clients\Ezcards\Products;

class GetProducts
{
    public function __construct(private Products $ezcardsProducts) {}

    public function execute(int $limit, int $page): array
    {
        $queryParams = [
            'limit' => $limit,
            'page' => $page,
        ];
        return $this->ezcardsProducts->fetchList($queryParams);
    }
}
