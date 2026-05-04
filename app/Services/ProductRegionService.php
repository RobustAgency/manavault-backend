<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Repositories\ProductRepository;

class ProductRegionService
{
    public function __construct(
        private ProductRepository $repository,
    ) {}

    /**
     * @return Collection<int, string>
     */
    public function getUniqueRegions(?string $search = null): Collection
    {
        return $this->repository
            ->getAllProductsRegions()
            ->flatten()
            ->unique()
            ->when(
                $search,
                fn ($regions) => $regions->filter(
                    fn ($region) => str_contains(strtolower($region), strtolower($search))
                )
            )
            ->values();
    }
}
