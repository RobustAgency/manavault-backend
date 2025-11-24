<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherAuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_it_can_list_all_voucher_audit_logs()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(5)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson('/api/admin/voucher-audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'voucher_id',
                            'user',
                            'action',
                            'ip_address',
                            'user_agent',
                            'created_at',
                        ],
                    ],
                    'per_page',
                    'total',
                    'last_page',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Voucher audit logs retrieved successfully.',
            ]);

        $this->assertEquals(5, $response->json('data.total'));
    }

    public function test_it_can_filter_audit_logs_by_date_range()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        // Create logs with different dates
        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        VoucherAuditLog::factory()->count(3)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(3),
        ]);

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson("/api/admin/voucher-audit-logs?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('data.total'));
    }

    public function test_it_supports_pagination()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(25)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson('/api/admin/voucher-audit-logs?per_page=10&page=1');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('data.per_page'));
        $this->assertEquals(1, $response->json('data.current_page'));
        $this->assertEquals(25, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
        $this->assertCount(10, $response->json('data.data'));
    }

    public function test_it_orders_logs_by_created_at_descending()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        $log1 = VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subHours(2),
        ]);

        $log2 = VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subHours(1),
        ]);

        $log3 = VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson('/api/admin/voucher-audit-logs');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertEquals($log3->id, $data[0]['id']);
        $this->assertEquals($log2->id, $data[1]['id']);
        $this->assertEquals($log1->id, $data[2]['id']);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson('/api/admin/voucher-audit-logs');

        $response->assertStatus(401);
    }

    public function test_it_validates_date_range()
    {
        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson('/api/admin/voucher-audit-logs?start_date=2025-12-01&end_date=2025-11-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_it_includes_user_and_voucher_relationships()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->admin, 'supabase')
            ->getJson('/api/admin/voucher-audit-logs');

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals($user->name, $data['user']['name']);
        $this->assertEquals($user->email, $data['user']['email']);
    }
}
