<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\IrewardifyWebhookRequest;

class IrewardifyWebhookController extends Controller
{
    public function handle(IrewardifyWebhookRequest $request): JsonResponse
    {
        Log::info('Irewardify webhook received', $request->validated());

        return response()->json([
            'message' => 'Webhook received.',
            'error' => false,
        ]);
    }
}
