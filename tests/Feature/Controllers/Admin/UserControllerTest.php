<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_super_admin_can_view_all_users_with_pagination(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $users = User::factory()->count(5)->create(['role' => UserRole::USER->value]);
        $role = Role::create(['name' => 'user']);
        foreach ($users as $user) {
            $user->assignRole($role);
        }
        $response = $this->actingAs($admin)->getJson('/api/users');
        $response->assertOk();

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('Users retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
    }

    public function test_index_excludes_users_who_are_super_admin_by_legacy_role_or_spatie_role(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $regularUser = User::factory()->create(['role' => UserRole::USER->value]);
        $legacySuperAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $spatieSuperAdmin = User::factory()->create(['role' => UserRole::USER->value]);
        $superAdminRole = Role::create(['name' => UserRole::SUPER_ADMIN->value]);
        $spatieSuperAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($admin)->getJson('/api/users');
        $response->assertOk();

        $returnedIds = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($returnedIds->contains($regularUser->id));
        $this->assertFalse($returnedIds->contains($legacySuperAdmin->id));
        $this->assertFalse($returnedIds->contains($spatieSuperAdmin->id));
        $this->assertFalse($returnedIds->contains($admin->id));
    }

    public function test_super_admin_can_view_user(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::USER->value]);

        $response = $this->actingAs($admin)->getJson("/api/users/{$user->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
            ],
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('User retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
    }
}
