<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAuditLog;
use App\Enums\VoucherAuditActions;
use App\Repositories\VoucherAuditLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherAuditLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VoucherAuditLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VoucherAuditLogRepository;
    }

    public function test_it_can_get_all_audit_logs_with_pagination()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(10)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $result = $this->repository->getFilteredLogs([]);

        $this->assertCount(10, $result->items());
        $this->assertEquals(10, $result->total());
    }

    public function test_it_filters_by_action()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(6)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'action' => VoucherAuditActions::VIEWED->value,
        ]);

        VoucherAuditLog::factory()->count(4)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'action' => VoucherAuditActions::COPIED->value,
        ]);

        $result = $this->repository->getFilteredLogs(['action' => VoucherAuditActions::VIEWED->value]);

        $this->assertEquals(6, $result->total());
        foreach ($result->items() as $log) {
            $this->assertEquals(VoucherAuditActions::VIEWED->value, $log->action);
        }
    }

    public function test_it_filters_by_start_date()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(2)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        VoucherAuditLog::factory()->count(5)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        $startDate = now()->subDays(3)->format('Y-m-d');
        $result = $this->repository->getFilteredLogs(['start_date' => $startDate]);

        $this->assertEquals(5, $result->total());
    }

    public function test_it_filters_by_end_date()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(3)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        VoucherAuditLog::factory()->count(4)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $endDate = now()->subDays(5)->format('Y-m-d');
        $result = $this->repository->getFilteredLogs(['end_date' => $endDate]);

        $this->assertEquals(3, $result->total());
    }

    public function test_it_filters_by_date_range()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(15),
        ]);

        VoucherAuditLog::factory()->count(3)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        VoucherAuditLog::factory()->count(2)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->subDays(1)->format('Y-m-d');

        $result = $this->repository->getFilteredLogs([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $this->assertEquals(5, $result->total());
    }

    public function test_it_respects_custom_per_page()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(30)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $result = $this->repository->getFilteredLogs(['per_page' => 10]);

        $this->assertEquals(10, $result->perPage());
        $this->assertCount(10, $result->items());
        $this->assertEquals(30, $result->total());
    }

    public function test_it_uses_default_per_page_when_not_specified()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->count(20)->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $result = $this->repository->getFilteredLogs([]);

        $this->assertEquals(15, $result->perPage());
    }

    public function test_it_orders_results_by_created_at_descending()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        $log1 = VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'created_at' => now()->subHours(3),
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

        $result = $this->repository->getFilteredLogs([]);
        $items = $result->items();

        $this->assertEquals($log3->id, $items[0]->id);
        $this->assertEquals($log2->id, $items[1]->id);
        $this->assertEquals($log1->id, $items[2]->id);
    }

    public function test_it_eager_loads_voucher_relationship()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $result = $this->repository->getFilteredLogs([]);
        $log = $result->items()[0];

        $this->assertTrue($log->relationLoaded('voucher'));
        $this->assertEquals($voucher->id, $log->voucher->id);
    }

    public function test_it_eager_loads_user_relationship()
    {
        $voucher = Voucher::factory()->create();
        $user = User::factory()->create();

        VoucherAuditLog::factory()->create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
        ]);

        $result = $this->repository->getFilteredLogs([]);
        $log = $result->items()[0];

        $this->assertTrue($log->relationLoaded('user'));
        $this->assertEquals($user->id, $log->user->id);
    }
}
