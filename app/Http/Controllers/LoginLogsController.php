<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use Illuminate\Http\JsonResponse;
use App\Repositories\LoginLogsRepository;
use App\Http\Requests\ListLoginLogsRequest;
use App\Http\Requests\StoreLoginLogsRequest;

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

    public function store(StoreLoginLogsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        LoginLog::create($validatedData);

        return response()->json([
            'message' => 'Login log created successfully.',
            'error' => false,
        ]);
    }
}
