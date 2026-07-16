<?php

namespace App\Repositories;

use App\Models\Customer;

class CustomerRepository
{
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
