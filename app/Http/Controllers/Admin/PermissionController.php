<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\PermissionRepository;
use App\Http\Requests\Permission\ListPermissionsRequest;

class PermissionController extends Controller
{
    public function __construct(private PermissionRepository $permissionRepository) {}

    /**
     * Display a listing of permissions.
     */
    public function index(ListPermissionsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissions = $this->permissionRepository->getFilteredPermissions($validated);

        return response()->json([
            'error' => false,
            'data' => $permissions,
            'message' => 'Permissions retrieved successfully.',
        ]);
    }
}
