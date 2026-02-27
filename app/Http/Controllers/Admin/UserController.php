<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Enums\UserRole;
use App\Clients\SupabaseClient;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\AssignRolesRequest;
use App\Http\Requests\Admin\SearchUsersRequest;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private UserRepository $userRepository,
        private SupabaseClient $supabaseClient,
    ) {}

    /**
     * List all users with pagination.
     */
    public function index(SearchUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $users = $this->userRepository->searchPaginated($validated);

        return response()->json([
            'error' => false,
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    /**
     * Show a specific user with their details.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Approve a user account.
     */
    public function approve(User $user): JsonResponse
    {
        $user->approve();

        return response()->json([
            'error' => false,
            'message' => 'User approved successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Revoke approval for a user account.
     */
    public function revokeApproval(User $user): JsonResponse
    {
        $user->revokeApproval();

        return response()->json([
            'error' => false,
            'message' => 'User approval revoked successfully',
            'data' => null,
        ]);
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->syncRoles($validated['role_id']);

        return response()->json([
            'error' => false,
            'message' => 'Roles assigned successfully',
            'data' => new UserResource($user->load('roles')),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = Role::find($validated['role_id']);

        $supabaseData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role->name ?? UserRole::USER->value,
            'password' => $validated['password'],
        ];

        $supabaseClient = $this->supabaseClient->createUser($supabaseData);

        $user = User::createFromSupabase([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'supabase_id' => $supabaseClient['id'],
            'password' => bcrypt($validated['password']),
            'role' => $role->name ?? UserRole::USER->value,
        ]);

        $user->assignRole($role);

        return response()->json([
            'error' => false,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    public function destroy(User $user): JsonResponse
    {
        // Delete user from Supabase
        $this->supabaseClient->deleteUser($user->supabase_id);

        // Delete user from local database
        $user->delete();

        return response()->json([
            'error' => false,
            'message' => 'User deleted successfully',
            'data' => null,
        ]);
    }
}
