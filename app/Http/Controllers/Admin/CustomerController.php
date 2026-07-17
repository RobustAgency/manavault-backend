<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\CustomerRepository;
use App\Http\Requests\Customer\ListCustomerRequest;

class CustomerController extends Controller
{
    public function __construct(private CustomerRepository $customerRepository) {}

    public function index(ListCustomerRequest $request): JsonResponse
    {
        $customers = $this->customerRepository->getFilteredCustomers($request->validated());

        return response()->json([
            'error' => false,
            'data' => $customers,
            'message' => 'Customers retrieved successfully.',
        ]);
    }
}
