<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\PricingRuleService;
use App\Http\Requests\PriceRule\StorePriceRuleController;

class PriceRuleController extends Controller
{
    public function __construct(private PricingRuleService $pricingRuleService) {}

    public function store(StorePriceRuleController $request): JsonResponse
    {
        $data = $request->validated();
        $this->pricingRuleService->createPriceRuleWithConditions($data);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Price rule created successfully.',
        ]);
    }
}
