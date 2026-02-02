<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\ModuleRepository;
use App\Http\Requests\Module\ListModulesRequest;

class ModuleController extends Controller
{
    public function __construct(private ModuleRepository $moduleRepository) {}

    /**
     * Display a listing of modules with their permissions.
     */
    public function index(ListModulesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $modules = $this->moduleRepository->getFilteredModules($validated);

        return response()->json([
            'error' => false,
            'data' => $modules,
            'message' => 'Modules retrieved successfully.',
        ]);
    }
}
