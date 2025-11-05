<?php

namespace App\Repositories;

use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    /**
     * Get paginated suppliers.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function getPaginatedSuppliers(int $per_page = 10): LengthAwarePaginator
    {
        $query = Supplier::query();

        return $query->paginate($per_page);
    }

    public function createSupplier(array $data): Supplier
    {
        $data['slug'] = strtolower(str_replace(' ', '_', $data['name']));

        return Supplier::create($data);
    }

    public function updateSupplier(Supplier $supplier, array $data): bool
    {
        return $supplier->update($data);
    }

    public function deleteSupplier(Supplier $supplier): bool
    {
        return $supplier->delete();
    }
}
