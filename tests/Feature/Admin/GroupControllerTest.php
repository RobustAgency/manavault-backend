<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test getting paginated list of groups
     */
    public function test_admin_can_get_paginated_groups(): void
    {
        Group::factory()->count(20)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Groups retrieved successfully.',
            ]);
    }

    /**
     * Test filtering groups by name
     */
    public function test_admin_can_filter_groups_by_name(): void
    {
        Group::factory()->create(['name' => 'Admin Group']);
        Group::factory()->create(['name' => 'User Group']);
        Group::factory()->create(['name' => 'Admin Team']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/groups?name=Admin');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('error', false);

        $groups = $response->json('data.data');
        foreach ($groups as $group) {
            $this->assertStringContainsString('Admin', $group['name']);
        }
    }

    /**
     * Test custom pagination
     */
    public function test_admin_can_paginate_groups_with_custom_per_page(): void
    {
        Group::factory()->count(50)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/groups?per_page=25');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 50);
    }

    /**
     * Test storing a new group
     */
    public function test_admin_can_create_a_group(): void
    {
        $groupData = [
            'name' => 'New Group',
            'description' => 'A brand new group',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/groups', $groupData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Group created successfully.',
                'data' => $groupData,
            ]);

        $this->assertDatabaseHas('groups', $groupData);
    }

    /**
     * Test creating group with minimal data
     */
    public function test_admin_can_create_group_with_only_name(): void
    {
        $groupData = ['name' => 'Minimal Group'];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/groups', $groupData);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    'name' => 'Minimal Group',
                ],
            ]);

        $this->assertDatabaseHas('groups', $groupData);
    }

    /**
     * Test validation error when creating group without name
     */
    public function test_creation_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/groups', ['description' => 'No name provided']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test validation error for duplicate group name
     */
    public function test_creation_fails_with_duplicate_name(): void
    {
        Group::factory()->create(['name' => 'Existing Group']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/groups', [
                'name' => 'Existing Group',
                'description' => 'Duplicate name',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test showing a specific group
     */
    public function test_admin_can_show_a_group(): void
    {
        $group = Group::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Group retrieved successfully.',
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                ],
            ]);
    }

    /**
     * Test showing non-existent group returns 404
     */
    public function test_showing_non_existent_group_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/groups/99999');

        $response->assertStatus(404);
    }

    /**
     * Test updating a group
     */
    public function test_admin_can_update_a_group(): void
    {
        $group = Group::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/groups/{$group->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Group updated successfully.',
                'data' => $updateData,
            ]);

        $this->assertDatabaseHas('groups', $updateData);
    }

    /**
     * Test partial update (only name)
     */
    public function test_admin_can_update_only_group_name(): void
    {
        $group = Group::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/groups/{$group->id}", [
                'name' => 'New Name',
                'description' => 'Original Description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.description', 'Original Description');
    }

    /**
     * Test update validation error for duplicate name
     */
    public function test_update_fails_with_duplicate_name_from_another_group(): void
    {
        $group1 = Group::factory()->create(['name' => 'Group 1']);
        $group2 = Group::factory()->create(['name' => 'Group 2']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/groups/{$group2->id}", [
                'name' => 'Group 1',
                'description' => 'Updated',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test update allows same name for same group
     */
    public function test_update_allows_same_name_for_same_group(): void
    {
        $group = Group::factory()->create(['name' => 'Same Name']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/groups/{$group->id}", [
                'name' => 'Same Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'name' => 'Same Name',
                ],
            ]);
    }

    /**
     * Test deleting a group
     */
    public function test_admin_can_delete_a_group(): void
    {
        $group = Group::factory()->create();
        $groupId = $group->id;

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Group deleted successfully.',
            ]);

        $this->assertDatabaseMissing('groups', ['id' => $groupId]);
    }

    /**
     * Test deleting non-existent group returns 404
     */
    public function test_deleting_non_existent_group_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/admin/groups/99999');

        $response->assertStatus(404);
    }

    /**
     * Test JSON response structure
     */
    public function test_response_has_correct_json_structure(): void
    {
        $group = Group::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/groups/{$group->id}");

        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'description',
                'created_at',
                'updated_at',
                'roles',
            ],
        ]);
    }

    /**
     * Test empty group list
     */
    public function test_empty_group_list_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/groups');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Groups retrieved successfully.',
                'data' => [
                    'total' => 0,
                ],
            ]);
    }
}
