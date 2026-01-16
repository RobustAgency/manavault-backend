<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\Group;
use App\Repositories\GroupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private GroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GroupRepository;
    }

    /**
     * Test getting filtered groups with pagination
     */
    public function test_it_can_get_all_groups_with_pagination(): void
    {
        Group::factory()->count(20)->create();

        $result = $this->repository->getFilteredGroups(['per_page' => 15]);

        $this->assertCount(15, $result->items());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(2, $result->lastPage());
    }

    /**
     * Test filtering groups by name
     */
    public function test_it_can_filter_groups_by_name(): void
    {
        Group::factory()->create(['name' => 'Admin Group']);
        Group::factory()->create(['name' => 'User Group']);
        Group::factory()->create(['name' => 'Admin Team']);

        $result = $this->repository->getFilteredGroups(['name' => 'Admin']);

        $this->assertEquals(2, $result->total());
        foreach ($result->items() as $group) {
            $this->assertStringContainsString('Admin', $group->name);
        }
    }

    /**
     * Test default pagination
     */
    public function test_it_uses_default_pagination_when_not_specified(): void
    {
        Group::factory()->count(20)->create();

        $result = $this->repository->getFilteredGroups([]);

        $this->assertEquals(15, $result->perPage());
    }

    /**
     * Test creating a group
     */
    public function test_it_can_create_a_group(): void
    {
        $data = [
            'name' => 'Test Group',
            'description' => 'Test Description',
        ];

        $group = $this->repository->createGroup($data);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Test Group', $group->name);
        $this->assertEquals('Test Description', $group->description);
        $this->assertDatabaseHas('groups', $data);
    }

    /**
     * Test updating a group
     */
    public function test_it_can_update_a_group(): void
    {
        $group = Group::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ];

        $updatedGroup = $this->repository->updateGroup($group, $updatedData);

        $this->assertEquals('Updated Name', $updatedGroup->name);
        $this->assertEquals('Updated Description', $updatedGroup->description);
        $this->assertDatabaseHas('groups', $updatedData);
    }

    /**
     * Test deleting a group
     */
    public function test_it_can_delete_a_group(): void
    {
        $group = Group::factory()->create();
        $groupId = $group->id;

        $result = $this->repository->deleteGroup($group);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('groups', ['id' => $groupId]);
    }

    /**
     * Test groups are ordered by name
     */
    public function test_groups_are_ordered_by_name_ascending(): void
    {
        Group::factory()->create(['name' => 'Zebra Group']);
        Group::factory()->create(['name' => 'Apple Group']);
        Group::factory()->create(['name' => 'Mango Group']);

        $result = $this->repository->getFilteredGroups([]);
        $groups = $result->items();

        $this->assertEquals('Apple Group', $groups[0]->name);
        $this->assertEquals('Mango Group', $groups[1]->name);
        $this->assertEquals('Zebra Group', $groups[2]->name);
    }

    /**
     * Test creating group with minimal data
     */
    public function test_it_can_create_group_with_only_name(): void
    {
        $data = ['name' => 'Minimal Group'];

        $group = $this->repository->createGroup($data);

        $this->assertEquals('Minimal Group', $group->name);
        $this->assertNull($group->description);
    }

    /**
     * Test updating only description
     */
    public function test_it_can_update_only_description(): void
    {
        $group = Group::factory()->create(['name' => 'Test Group']);
        $originalName = $group->name;

        $updatedGroup = $this->repository->updateGroup($group, ['description' => 'New Description']);

        $this->assertEquals($originalName, $updatedGroup->name);
        $this->assertEquals('New Description', $updatedGroup->description);
    }
}
