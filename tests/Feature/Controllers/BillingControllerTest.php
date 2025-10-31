<?php

namespace Tests\Feature\Controllers;

use Mockery;
use Tests\TestCase;
use App\Models\Plan;
use App\Models\User;
use App\Enums\UserRole;
use Tests\Fakes\FakeSupabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/auth/v1/admin/users' => function ($request) {
                $requestData = $request->data();

                return Http::response(FakeSupabase::getUserCreationResponse([
                    'email' => $requestData['email'],
                    'name' => $requestData['user_metadata']['name'] ?? 'Test User',
                    'email_verified' => $requestData['email_confirm'] ?? true,
                ]), 200);
            },
        ]);
    }

    public function test_get_active_plans(): void
    {
        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        Plan::factory()->create([
            'id' => 1,
            'name' => 'Basic Plan',
            'stripe_price_id' => 'price_123',
            'active' => true,
        ]);
        $response = $this->actingAs($user)->getJson('/api/plans');

        $response->assertStatus(200);
        $response->assertJson([
            'error' => false,
            'message' => 'Plans retrieved successfully.',
        ]);
    }

    public function test_user_has_no_payment_method(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);
        $plan = Plan::factory()->create(['active' => true]);

        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('createOrGetStripeCustomer')->once();
        $userMock->shouldReceive('hasPaymentMethod')->once()->andReturn(false);
        $userMock->shouldReceive('billingPortalUrl')->once()->andReturn('http://fake-stripe-portal.test');

        $this->app->instance(User::class, $userMock);
        $response = $this->actingAs($userMock)->getJson("/api/plans/subscribe/{$plan->id}");

        $response->assertOk();
        $response->assertJson([
            'error' => true,
            'message' => 'You must add a payment method to subscribe.',
            'data' => ['redirect_url' => 'http://fake-stripe-portal.test'],
        ]);
    }

    public function test_get_user_invoices(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        // Fake Stripe invoice object
        $stripeInvoice = (object) [
            'number' => 'INV-001',
            'created' => now()->timestamp,
            'amount_paid' => 1000,
            'status' => 'paid',
            'invoice_pdf' => 'http://fake-invoice.pdf',
        ];

        // Mock Cashier Invoice wrapper
        $cashierInvoiceMock = Mockery::mock(\Laravel\Cashier\Invoice::class);
        $cashierInvoiceMock->shouldReceive('asStripeInvoice')->andReturn($stripeInvoice);

        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('invoices')->once()->andReturn([$cashierInvoiceMock]);

        $this->app->instance(User::class, $userMock);

        $response = $this->actingAs($userMock)->getJson('/api/plans/invoices');

        $response->assertOk()->assertJson([
            'error' => false,
            'message' => 'Invoices retrieved successfully.',
        ]);
    }

    public function test_get_user_upcoming_invoice(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        // Fake Stripe upcoming invoice object
        $stripeUpcomingInvoice = (object) [
            'number' => 'INV-UPCOMING-001',
            'created' => now()->timestamp,
            'amount_due' => 1500,
            'status' => 'open',
            'invoice_pdf' => null,
        ];

        // Mock Cashier Invoice wrapper
        $upcomingInvoiceMock = Mockery::mock(\Laravel\Cashier\Invoice::class);
        $upcomingInvoiceMock->shouldReceive('asStripeInvoice')->andReturn($stripeUpcomingInvoice);

        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('upcomingInvoice')->once()->andReturn($upcomingInvoiceMock);

        $this->app->instance(User::class, $userMock);

        $response = $this->actingAs($userMock)->getJson('/api/plans/upcoming-invoice');

        $response->assertOk()->assertJson([
            'error' => false,
            'message' => 'Upcoming invoice retrieved successfully.',
            'data' => [
                'invoice_number' => 'INV-UPCOMING-001',
                'amount_due' => 15,
                'status' => 'open',
            ],
        ]);
    }
}
