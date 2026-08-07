<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\LoginLogsRepository;
use App\Http\Requests\ListLoginLogsRequest;

class LoginLogsController extends Controller
{
    public function __construct(protected LoginLogsRepository $loginLogsRepository) {}

    public function index(ListLoginLogsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $loginLogs = $this->loginLogsRepository->getFilteredLoginLogs($filters);

        return response()->json([
            'data' => $loginLogs,
            'message' => 'Login logs retrieved successfully.',
            'error' => false,
        ]);
    }
}
