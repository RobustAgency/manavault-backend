<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Repositories\RoleRepository;
use App\Http\Requests\Role\ListRolesRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;

class RoleController extends Controller
{
    public function __construct(private RoleRepository $roleRepository) {}

    /**
     * Display a listing of roles.
     */
    public function index(ListRolesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $roles = $this->roleRepository->getFilteredRoles($validated);

        return response()->json([
            'error' => false,
            'data' => $roles,
            'message' => 'Roles retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created role with permissions and group.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = $this->roleRepository->createRole($validated);

        return response()->json([
            'error' => false,
            'data' => new RoleResource($role->load('permissions')),
            'message' => 'Role created successfully.',
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'error' => false,
            'data' => new RoleResource($role),
            'message' => 'Role retrieved successfully.',
        ]);
    }

    /**
     * Update the specified role with permissions and group.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        $updatedRole = $this->roleRepository->updateRole($role, $validated);

        return response()->json([
            'error' => false,
            'data' => new RoleResource($updatedRole->load('permissions')),
            'message' => 'Role updated successfully.',
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->roleRepository->deleteRole($role);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Role deleted successfully.',
        ]);
    }
}
