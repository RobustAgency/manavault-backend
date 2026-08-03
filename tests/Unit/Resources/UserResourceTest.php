<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_permissions_whose_module_no_longer_resolves(): void
    {
        $user = User::factory()->create();

        $validPermission = Permission::factory()->create(['guard_name' => 'supabase']);

        // Simulate a permission left pointing at a module row that no longer
        // exists (e.g. data inserted before module_id's foreign key was enforced).
        $orphanPermission = Permission::factory()->make([
            'guard_name' => 'supabase',
            'module_id' => 999999,
        ]);

        $user->setRelation('roles', collect());
        $user->setRelation('permissions', collect([$validPermission, $orphanPermission]));

        $data = (new UserResource($user))->toArray(Request::create('/'));

        $this->assertCount(1, $data['modules']);
        $this->assertEquals($validPermission->module_id, $data['modules'][0]['id']);
    }
}
