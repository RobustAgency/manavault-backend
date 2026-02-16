<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\LoginLog;
use App\Repositories\LoginLogsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginLogsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private LoginLogsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LoginLogsRepository;
    }

    public function test_returns_all_login_logs_with_pagination(): void
    {
        LoginLog::factory()->count(20)->create();

        $result = $this->repository->getFilteredLoginLogs([]);

        $this->assertEquals(15, $result->perPage()); // Default per_page is 15
        $this->assertEquals(20, $result->total());
        $this->assertCount(15, $result->items());
    }

    public function test_filters_by_email(): void
    {
        $targetEmail = 'test@example.com';
        LoginLog::factory()->forEmail($targetEmail)->count(3)->create();
        LoginLog::factory()->count(5)->create();

        $result = $this->repository->getFilteredLoginLogs(['email' => $targetEmail]);

        $this->assertEquals(3, $result->total());
        foreach ($result->items() as $log) {
            $this->assertEquals($targetEmail, $log->email);
        }
    }

    public function test_filters_by_ip_address(): void
    {
        $targetIp = '192.168.1.100';
        LoginLog::factory()->fromIp($targetIp)->count(4)->create();
        LoginLog::factory()->count(6)->create();

        $result = $this->repository->getFilteredLoginLogs(['ip_address' => $targetIp]);

        $this->assertEquals(4, $result->total());
        foreach ($result->items() as $log) {
            $this->assertEquals($targetIp, $log->ip_address);
        }
    }

    public function test_respects_custom_per_page_parameter(): void
    {
        LoginLog::factory()->count(30)->create();

        $result = $this->repository->getFilteredLoginLogs(['per_page' => 10]);

        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(30, $result->total());
        $this->assertCount(10, $result->items());
    }

    public function test_combines_multiple_filters(): void
    {
        $targetEmail = 'admin@example.com';
        $targetIp = '10.0.0.1';

        LoginLog::factory()->forEmail($targetEmail)->fromIp($targetIp)->count(2)->create(['activity' => 'login']);
        LoginLog::factory()->forEmail($targetEmail)->count(3)->create(['activity' => 'logout']);
        LoginLog::factory()->fromIp($targetIp)->count(4)->create();
        LoginLog::factory()->count(5)->create();

        $result = $this->repository->getFilteredLoginLogs([
            'email' => $targetEmail,
            'ip_address' => $targetIp,
            'activity' => 'login',
        ]);

        $this->assertEquals(2, $result->total());
        foreach ($result->items() as $log) {
            $this->assertEquals($targetEmail, $log->email);
            $this->assertEquals($targetIp, $log->ip_address);
            $this->assertStringContainsString('login', $log->activity);
        }
    }

    public function test_returns_empty_result_when_no_matches(): void
    {
        LoginLog::factory()->count(5)->create();

        $result = $this->repository->getFilteredLoginLogs(['email' => 'nonexistent@example.com']);

        $this->assertEquals(0, $result->total());
        $this->assertCount(0, $result->items());
    }

    public function test_ignores_empty_filter_values(): void
    {
        LoginLog::factory()->count(10)->create();

        $result = $this->repository->getFilteredLoginLogs([
            'email' => '',
            'ip_address' => null,
            'activity' => '',
        ]);

        $this->assertEquals(10, $result->total());
    }

    public function test_pagination_works_correctly_with_filters(): void
    {
        $targetEmail = 'test@example.com';
        LoginLog::factory()->forEmail($targetEmail)->count(25)->create();
        LoginLog::factory()->count(10)->create();

        $result = $this->repository->getFilteredLoginLogs([
            'email' => $targetEmail,
            'per_page' => 10,
        ]);

        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(3, $result->lastPage());
        $this->assertCount(10, $result->items());
    }

    public function test_returns_logs_ordered_by_latest_first(): void
    {
        $oldLog = LoginLog::factory()->create(['logged_in_at' => now()->subDays(5)]);
        $recentLog = LoginLog::factory()->create(['logged_in_at' => now()->subDay()]);
        $newestLog = LoginLog::factory()->create(['logged_in_at' => now()]);

        $result = $this->repository->getFilteredLoginLogs([]);

        $items = $result->items();
        $this->assertEquals($newestLog->id, $items[0]->id);
        $this->assertEquals($recentLog->id, $items[1]->id);
        $this->assertEquals($oldLog->id, $items[2]->id);
    }
}
