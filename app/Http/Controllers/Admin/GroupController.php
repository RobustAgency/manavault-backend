<?php

namespace App\Http\Controllers\Admin;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Repositories\GroupRepository;
use App\Http\Requests\Group\ListGroupsRequest;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;

class GroupController extends Controller
{
    public function __construct(private GroupRepository $groupRepository) {}

    /**
     * Display a listing of groups.
     */
    public function index(ListGroupsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $groups = $this->groupRepository->getFilteredGroups($validated);

        return response()->json([
            'error' => false,
            'data' => $groups,
            'message' => 'Groups retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created group.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $group = $this->groupRepository->createGroup($validated);

        return response()->json([
            'error' => false,
            'data' => new GroupResource($group),
            'message' => 'Group created successfully.',
        ], 201);
    }

    /**
     * Display the specified group.
     */
    public function show(Group $group): JsonResponse
    {
        $group->load('roles.permissions');

        return response()->json([
            'error' => false,
            'data' => new GroupResource($group),
            'message' => 'Group retrieved successfully.',
        ]);
    }

    /**
     * Update the specified group.
     */
    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();
        $updatedGroup = $this->groupRepository->updateGroup($group, $validated);

        return response()->json([
            'error' => false,
            'data' => new GroupResource($updatedGroup),
            'message' => 'Group updated successfully.',
        ]);
    }

    /**
     * Remove the specified group.
     */
    public function destroy(Group $group): JsonResponse
    {
        $this->groupRepository->deleteGroup($group);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Group deleted successfully.',
        ]);
    }
}
