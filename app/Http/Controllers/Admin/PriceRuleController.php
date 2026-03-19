<?php

namespace App\Http\Controllers\Admin;

use App\Models\PriceRule;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\PricingRuleService;
use App\Http\Resources\PriceRuleResource;
use App\Repositories\PriceRuleRepository;
use App\Http\Requests\PriceRule\ListPriceRuleRequest;
use App\Repositories\PriceRuleDigitalProductRepository;
use App\Http\Requests\PriceRule\StorePriceRuleController;

class PriceRuleController extends Controller
{
    public function __construct(
        private PricingRuleService $pricingRuleService,
        private PriceRuleRepository $priceRuleRepository,
        private PriceRuleDigitalProductRepository $priceRuleDigitalProductRepository,
    ) {}

    public function index(ListPriceRuleRequest $request): JsonResponse
    {
        $priceRules = $this->priceRuleRepository->getFilteredPriceRules($request->validated());

        return response()->json([
            'error' => false,
            'data' => $priceRules,
            'message' => 'Price rules retrieved successfully.',
        ]);
    }

    public function store(StorePriceRuleController $request): JsonResponse
    {
        $data = $request->validated();
        $this->pricingRuleService->createPriceRuleWithConditions($data);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Price rule applied successfully.',
        ]);
    }

    public function show(PriceRule $priceRule): JsonResponse
    {
        $priceRule->load('conditions');

        return response()->json([
            'error' => false,
            'data' => new PriceRuleResource($priceRule),
            'message' => 'Price rule retrieved successfully.',
        ]);
    }

    public function update(StorePriceRuleController $request, PriceRule $priceRule): JsonResponse
    {
        $data = $request->validated();
        $this->pricingRuleService->updatePriceRuleWithConditions($priceRule, $data);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Price rule updated successfully.',
        ]);
    }

    public function destroy(PriceRule $priceRule): JsonResponse
    {
        $this->priceRuleRepository->deletePriceRule($priceRule);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Price rule deleted successfully.',
        ]);
    }

    public function preview(StorePriceRuleController $request): JsonResponse
    {
        $data = $request->validated();
        $preview = $this->pricingRuleService->previewPriceRuleEffect($data);

        return response()->json([
            'error' => false,
            'data' => $preview,
            'message' => 'Price rule preview retrieved successfully.',
        ]);
    }

    public function postViewDigitalProducts(PriceRule $priceRule): JsonResponse
    {
        $digitalProducts = $this->priceRuleDigitalProductRepository->getByPriceRule($priceRule->id);

        return response()->json([
            'error' => false,
            'data' => $digitalProducts,
            'message' => 'Price rule digital products retrieved successfully.',
        ]);
    }
}
