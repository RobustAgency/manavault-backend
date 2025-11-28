<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\DigitalStockRepository;
use App\Http\Requests\Admin\ListDigitalStockRequest;

class DigitalStockController extends Controller
{
    public function __construct(private DigitalStockRepository $repository) {}

    public function index(ListDigitalStockRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $digitalStocks = $this->repository->getPaginatedDigitalStocks($filters);

        return response()->json([
            'error' => false,
            'data' => $digitalStocks,
            'message' => 'Digital stocks retrieved successfully.',
        ]);
    }

    public function lowStockProducts(ListDigitalStockRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $lowStockProducts = $this->repository->getLowDigitalStocks($filters);

        return response()->json([
            'error' => false,
            'data' => $lowStockProducts,
            'message' => 'Low stock digital products retrieved successfully.',
        ]);
    }
}
