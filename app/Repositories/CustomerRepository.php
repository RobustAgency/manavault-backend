<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    /**
     * Get paginated customers filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function getFilteredCustomers(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query();

        if (isset($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('company_name', 'like', '%'.$search.'%')
                    ->orWhere('company_email', 'like', '%'.$search.'%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create or update a customer, keyed by their external ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOrCreateByExternalId(array $data): Customer
    {
        return Customer::updateOrCreate(
            ['external_id' => $data['external_id']],
            [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'company_email' => $data['company_email'] ?? null,
            ],
        );
    }
}
