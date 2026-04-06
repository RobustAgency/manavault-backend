<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Clients\Giftery\Client as GifteryClient;

class GifteryController extends Controller
{
    public function __construct(
        private GifteryClient $gifteryClient,
    ) {}

    /**
     * Test endpoint to reserve an order from Giftery.
     * This endpoint does not require authentication for testing purposes.
     */
    public function testReserveOrder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'itemId' => 'required|integer',
                'fields' => 'required|array',
                'fields.*.key' => 'required|string',
                'fields.*.value' => 'required|string',
            ]);

            $response = $this->gifteryClient->reserveOrder([
                'itemId' => $validated['itemId'],
                'fields' => $validated['fields'],
            ]);

            return response()->json([
                'error' => false,
                'data' => $response,
                'message' => 'Order reserved successfully from Giftery.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test endpoint to get products from Giftery.
     * This endpoint does not require authentication for testing purposes.
     */
    public function testGetProducts(): JsonResponse
    {
        try {
            $response = $this->gifteryClient->getProducts();

            return response()->json([
                'error' => false,
                'data' => $response,
                'message' => 'Products retrieved successfully from Giftery.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function testGetAccounts(): JsonResponse
    {
        try {
            $response = $this->gifteryClient->getAccount();

            return response()->json([
                'error' => false,
                'data' => $response,
                'message' => 'Account information retrieved successfully from Giftery.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
