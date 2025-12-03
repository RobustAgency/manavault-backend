<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Enums\SupplierType;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    /**
     * Get paginated suppliers.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function getFilteredSuppliers(array $filters = []): LengthAwarePaginator
    {
        $query = Supplier::query();

        // Apply filters to the query
        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($per_page);
    }

    public function createSupplier(array $data): Supplier
    {
        $data['slug'] = strtolower(str_replace(' ', '_', $data['name']));
        $data['type'] = SupplierType::INTERNAL->value;

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
