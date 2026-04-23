# Ezcards Supplier Order Handler — Implementation Document

## Overview

This document describes the concrete steps to implement the Supplier Order Handler architecture
(defined in `docs/supplier-order-handler-architecture.md`) **specifically for the Ezcards supplier**.

Ezcards places an order synchronously (the API responds immediately with a `transactionId`), but
voucher codes are **not** returned in the same response — they are fetched separately via a
subsequent API call. Therefore `EzcardsOrderHandler` implements only
`SupplierOrderHandlerInterface` and returns `SupplierOrderResult::processing()`.

---

## Current State (What Already Exists)

| What | Location | Notes |
|---|---|---|
| HTTP client base | `app/Clients/Ezcards/Client.php` | Extends `BaseApiClient`; config prefix `services.ez_cards` |
| Orders client | `app/Clients/Ezcards/Orders.php` | Wraps `POST /v2/orders` |
| Vouchers client | `app/Clients/Ezcards/Vouchers.php` | Used for voucher retrieval |
| Products client | `app/Clients/Ezcards/Products.php` | Used for product sync |
| Place-order action | `app\Actions\Ezcards\PlaceOrder.php` | Calls `Orders::placeOrder()` |
| Place-order service | `app/Services/Ezcards/EzcardsPlaceOrderService.php` | Formats payload, calls action |
| Job (dispatcher) | `app/Jobs/PlaceExternalPurchaseOrderJob.php` | Calls `PurchaseOrderPlacementService::placeOrder()` |
| Placement service | `app/Services/PurchaseOrder/PurchaseOrderPlacementService.php` | Routes by `$supplier->slug` |

The goal is to **introduce** `EzcardsOrderHandler` as the new canonical integration point,
wire it into `SupplierOrderManager`, and eventually replace the slug-based `if` chain in
`PurchaseOrderPlacementService` / `PlaceExternalPurchaseOrderJob` for Ezcards.

---

## Files to Create

```
app/
├── Contracts/
│   └── Suppliers/
│       ├── SupplierOrderHandlerInterface.php   ← NEW
│       ├── PollableSupplierInterface.php        ← NEW (not used by Ezcards, needed by Manager)
│       └── WebhookSupplierInterface.php         ← NEW (not used by Ezcards, needed by Manager)
│
├── DTOs/
│   └── SupplierOrderResult.php                  ← NEW
│
├── Exceptions/
│   └── SupplierOrderException.php               ← NEW
│
├── Managers/
│   └── SupplierOrderManager.php                 ← NEW
│
└── Suppliers/
    └── EzcardsOrderHandler.php                  ← NEW
```

`AppServiceProvider` also needs a singleton registration (one line addition).

---

## Step 1 — Create the Interfaces

### `app/Contracts/Suppliers/SupplierOrderHandlerInterface.php`

```php
<?php

namespace App\Contracts\Suppliers;

use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

interface SupplierOrderHandlerInterface
{
    /**
     * Place an order with the supplier.
     *
     * @throws \App\Exceptions\SupplierOrderException
     */
    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
```

### `app/Contracts/Suppliers/PollableSupplierInterface.php`

```php
<?php

namespace App\Contracts\Suppliers;

use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

interface PollableSupplierInterface extends SupplierOrderHandlerInterface
{
    /**
     * Poll the supplier for the status of a previously placed order.
     *
     * @throws \App\Exceptions\SupplierOrderException
     */
    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
```

---

## Step 2 — Create the DTO

### `app/DTOs/SupplierOrderResult.php`

```php
<?php

namespace App\DTOs;

use App\Enums\PurchaseOrderSupplierStatus;
use App\Models\PurchaseOrderSupplier;

final class SupplierOrderResult
{
    public function __construct(
        private readonly PurchaseOrderSupplierStatus $status,
        private readonly ?PurchaseOrderSupplier $order = null,
    ) {}

    public static function completed(PurchaseOrderSupplier $order): self
    {
        return new self(
            status: PurchaseOrderSupplierStatus::COMPLETED,
            order: $order,
        );
    }

    public static function processing(): self
    {
        return new self(status: PurchaseOrderSupplierStatus::PROCESSING);
    }

    public static function failed(): self
    {
        return new self(status: PurchaseOrderSupplierStatus::FAILED);
    }

    public function getStatus(): PurchaseOrderSupplierStatus
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::PROCESSING;
    }

    public function isFailed(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::FAILED;
    }

    /** Populated only when isCompleted() === true. */
    public function getOrder(): ?PurchaseOrderSupplier
    {
        return $this->order;
    }
}
```

---

## Step 3 — Create the Exception

### `app/Exceptions/SupplierOrderException.php`

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class SupplierOrderException extends RuntimeException {}
```

---

## Step 4 — Create the Ezcards Handler

### `app/Suppliers/EzcardsOrderHandler.php`

**Fulfillment mode:** The Ezcards API responds synchronously with a `transactionId`, but voucher
codes are delivered asynchronously (fetched by a separate job/poller). The handler therefore
returns `SupplierOrderResult::processing()` and stores the `transactionId` on the
`PurchaseOrderSupplier` record.

```php
<?php

namespace App\Suppliers;

use App\Clients\Ezcards\Orders;
use App\Contracts\Suppliers\SupplierOrderHandlerInterface;
use App\DTOs\SupplierOrderResult;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Exceptions\SupplierOrderException;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Support\Facades\Log;

class EzcardsOrderHandler implements SupplierOrderHandlerInterface
{
    public function __construct(
        private readonly Orders $ordersClient,
    ) {}

    /**
     * Place an order with Ezcards.
     *
     * Builds the product payload from the PurchaseOrderItems linked to the
     * given PurchaseOrderSupplier, calls the Ezcards API, stores the returned
     * transactionId, and returns a PROCESSING result.
     *
     * Vouchers must be fetched separately (e.g., via GetVoucherCodes action).
     *
     * @throws SupplierOrderException
     */
    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        /** @var \App\Models\PurchaseOrder $purchaseOrder */
        $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;

        $items = $purchaseOrderSupplier->purchaseOrderItems()
            ->with('digitalProduct')
            ->get();

        $products = $items->map(fn (PurchaseOrderItem $item) => [
            'sku'      => $item->digitalProduct->sku,
            'quantity' => $item->quantity,
        ])->all();

        $payload = [
            'clientOrderNumber'              => $purchaseOrder->order_number,
            'enableClientOrderNumberDupCheck' => false,
            'products'                       => $products,
            'payWithCurrency'                => strtoupper($purchaseOrder->currency ?? 'USD'),
        ];

        try {
            $response      = $this->ordersClient->placeOrder($payload);
            $transactionId = $response['transactionId'] ?? null;
        } catch (\Throwable $e) {
            $purchaseOrderSupplier->update([
                'status' => PurchaseOrderSupplierStatus::FAILED->value,
            ]);

            Log::error('EzCards placeOrder failed', [
                'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                'error'                      => $e->getMessage(),
            ]);

            throw new SupplierOrderException(
                "EzCards order failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $purchaseOrderSupplier->update([
            'transaction_id' => $transactionId,
            'status'         => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        Log::info('EzCards order placed — awaiting voucher retrieval', [
            'purchase_order_id'          => $purchaseOrder->id,
            'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
            'transaction_id'             => $transactionId,
        ]);

        return SupplierOrderResult::processing();
    }
}
```

**Key points:**

- Injects `App\Clients\Ezcards\Orders` (already exists) — no new HTTP client needed.
- Loads `purchaseOrderItems` with `digitalProduct` via the existing `purchaseOrderItems()` relation on `PurchaseOrderSupplier`.
- On API failure: updates status to `FAILED`, logs the error, rethrows as `SupplierOrderException`.
- On success: stores `transactionId`, sets status to `PROCESSING`, returns `SupplierOrderResult::processing()`.
- Does **not** update status to `COMPLETED` — that is handled by the existing voucher-retrieval flow (`GetVoucherCodes` action).

---

## Step 5 — Create the Manager

### Design Goals

`SupplierOrderManager` is the **single entry point for all supplier order dispatch**, present and
future. It replaces the `if/elseif` slug chain that currently lives in
`PlaceExternalPurchaseOrderJob`. Every supplier — Ezcards, Gift2Games, Giftery, Irewardify,
Tikkery, and any supplier added later — will be registered as a named driver here.

**Adding a new supplier = one new `createXxxDriver()` method + one new slug in
`getRegisteredSlugs()`**. No other class needs to change.

### Supplier → Handler mapping (current + future)

| Supplier slug(s) | Handler class (to create) | Implements |
|---|---|---|
| `ez_cards` | `EzcardsOrderHandler` ✅ (this document) | `SupplierOrderHandlerInterface` |
| `gift2games`, `gift-2-games` | `Gift2GamesOrderHandler` (future doc) | `SupplierOrderHandlerInterface` |
| `giftery-api` | `GifteryOrderHandler` (future doc) | `SupplierOrderHandlerInterface` |
| `irewardify` | `IrewardifyOrderHandler` (future doc) | `SupplierOrderHandlerInterface` |
| `tikkery` | `TikkeryOrderHandler` (future doc) | `PollableSupplierInterface` |

> Suppliers whose vouchers are delivered asynchronously and need periodic polling (e.g., Tikkery)
> should implement `PollableSupplierInterface`. Suppliers that push results via webhook implement
> `WebhookSupplierInterface`. Ezcards voucher retrieval is handled by a separate existing job, so
> it implements only the base interface.

### `app/Managers/SupplierOrderManager.php`

```php
<?php

namespace App\Managers;

use App\Contracts\Suppliers\PollableSupplierInterface;
use App\Contracts\Suppliers\SupplierOrderHandlerInterface;
use App\Contracts\Suppliers\WebhookSupplierInterface;
use App\Suppliers\EzcardsOrderHandler;
use Illuminate\Support\Manager;

class SupplierOrderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'ez_cards';
    }

    // ── Driver factory methods ──────────────────────────────────────
    // Each method follows the naming convention: createXxxDriver()
    // where "Xxx" is the slug with underscores removed and first letter
    // of each word capitalised (Laravel Manager convention).
    // ----------------------------------------------------------------

    protected function createEzCardsDriver(): EzcardsOrderHandler
    {
        return $this->container->make(EzcardsOrderHandler::class);
    }

    // Future drivers — uncomment and implement when the corresponding
    // handler class is created:
    //
    // protected function createGift2gamesDriver(): Gift2GamesOrderHandler
    // {
    //     return $this->container->make(Gift2GamesOrderHandler::class);
    // }
    //
    // protected function createGifteryApiDriver(): GifteryOrderHandler
    // {
    //     return $this->container->make(GifteryOrderHandler::class);
    // }
    //
    // protected function createIrewardifyDriver(): IrewardifyOrderHandler
    // {
    //     return $this->container->make(IrewardifyOrderHandler::class);
    // }
    //
    // protected function createTikkeryDriver(): TikkeryOrderHandler
    // {
    //     return $this->container->make(TikkeryOrderHandler::class);
    // }

    // ── Supplier support check ─────────────────────────────────────

    /**
     * Returns true if a handler is registered for the given slug.
     * Use this in the job to decide whether to route through the manager.
     */
    public function supports(string $slug): bool
    {
        return in_array($slug, $this->getRegisteredSlugs(), true);
    }

    // ── Filtered driver collections ────────────────────────────────

    /**
     * @return array<string, PollableSupplierInterface>
     */
    public function getPollableDrivers(): array
    {
        return $this->getDriversByInterface(PollableSupplierInterface::class);
    }

    /**
     * @return array<string, WebhookSupplierInterface>
     */
    public function getWebhookDrivers(): array
    {
        return $this->getDriversByInterface(WebhookSupplierInterface::class);
    }

    /**
     * @param  class-string  $interface
     * @return array<string, SupplierOrderHandlerInterface>
     */
    private function getDriversByInterface(string $interface): array
    {
        $matched = [];

        foreach ($this->getRegisteredSlugs() as $slug) {
            $driver = $this->driver($slug);
            if ($driver instanceof $interface) {
                $matched[$slug] = $driver;
            }
        }

        return $matched;
    }

    /**
     * All registered slugs. Must stay in sync with createXxxDriver() methods.
     * Gift2Games has two legacy slug variants — both must be listed.
     *
     * @return string[]
     */
    private function getRegisteredSlugs(): array
    {
        return [
            'ez_cards',
            // 'gift2games',
            // 'gift-2-games',
            // 'giftery-api',
            // 'irewardify',
            // 'tikkery',
        ];
    }
}
```

> **Naming convention:** Laravel's `Manager` derives the factory method name from the driver slug
> by stripping underscores/hyphens and uppercasing each word: `ez_cards` → `createEzCardsDriver`,
> `gift-2-games` → `createGift2GamesDriver`, `giftery-api` → `createGifteryApiDriver`. The method
> name must follow this pattern exactly or Laravel will throw an `InvalidArgumentException`.

---

## Step 6 — Register the Manager as a Singleton

In `app/Providers/AppServiceProvider.php`, add to the `register()` method:

```php
use App\Managers\SupplierOrderManager;

public function register(): void
{
    // ...existing bindings...

    $this->app->singleton(SupplierOrderManager::class, function ($app) {
        return new SupplierOrderManager($app);
    });
}
```

---

## Step 7 — Wire the Manager into the Job (All Suppliers)

The goal of this step is to replace the entire slug-based `if/elseif` chain with a single
manager dispatch. Suppliers that are already registered in `SupplierOrderManager` are routed
through their handler; unregistered suppliers fall back to the legacy `PurchaseOrderPlacementService`
path, so migration can happen supplier-by-supplier with zero breakage.

### Strategy

```
supplier slug
      │
      ▼
manager.supports(slug)?
      │
   YES │                     NO │
      ▼                         ▼
manager.driver(slug)       legacy PurchaseOrderPlacementService
      │                    (existing if/elseif chain — untouched)
      ▼
handler.placeOrder(purchaseOrderSupplier)
      │
      ▼
SupplierOrderResult  (PROCESSING | COMPLETED | FAILED)
      │
      ▼
purchaseOrderStatusService.updateStatus(...)
```

### Before (current `PlaceExternalPurchaseOrderJob::handle()`)

```php
try {
    $externalOrderResponse = $purchaseOrderPlacementService->placeOrder(
        $this->supplier,
        $this->purchaseOrderItems,
        $this->orderNumber,
        $this->currency,
    );
    $transactionId = $externalOrderResponse['transactionId'] ?? null;
    $this->purchaseOrderSupplier->update(['transaction_id' => $transactionId]);
} catch (\Exception $e) {
    $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);
    $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
    return;
}

if ($this->isGift2GamesSupplier()) {
    // ...
} elseif ($this->supplier->slug === 'giftery-api') {
    // ...
} elseif ($this->supplier->slug === 'ez_cards') {
    // ...
} elseif ($this->supplier->slug === 'irewardify') {
    // ...
} elseif ($this->supplier->slug === 'tikkery') {
    // ...
}

$purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
```

### After (manager-first, legacy fallback)

```php
use App\Managers\SupplierOrderManager;
use App\Exceptions\SupplierOrderException;

public function handle(
    PurchaseOrderPlacementService $purchaseOrderPlacementService,
    SupplierOrderManager $supplierOrderManager,
    Gift2GamesVoucherService $gift2GamesVoucherService,
    GifteryVoucherService $gifteryVoucherService,
    TikkeryVoucherService $tikkeryVoucherService,
    PurchaseOrderStatusService $purchaseOrderStatusService,
): void {
    // ── New path: manager-registered suppliers ─────────────────────
    if ($supplierOrderManager->supports($this->supplier->slug)) {
        try {
            $supplierOrderManager
                ->driver($this->supplier->slug)
                ->placeOrder($this->purchaseOrderSupplier);
        } catch (SupplierOrderException $e) {
            // Handler has already set status = FAILED and logged the error.
            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
            return;
        }

        $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
        return;
    }

    // ── Legacy path: suppliers not yet migrated to the manager ─────
    $externalOrderResponse = [];
    $transactionId = null;

    try {
        $externalOrderResponse = $purchaseOrderPlacementService->placeOrder(
            $this->supplier,
            $this->purchaseOrderItems,
            $this->orderNumber,
            $this->currency,
        );
        $transactionId = $externalOrderResponse['transactionId'] ?? null;
        $this->purchaseOrderSupplier->update(['transaction_id' => $transactionId]);
    } catch (\Exception $e) {
        $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);
        $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
        Log::error('Failed to place external order', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_slug'     => $this->supplier->slug,
            'error'             => $e->getMessage(),
        ]);
        return;
    }

    if ($this->isGift2GamesSupplier()) {
        $gift2GamesVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
        $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
    } elseif ($this->supplier->slug === 'giftery-api') {
        $gifteryVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
        $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
    } elseif ($this->supplier->slug === 'irewardify') {
        Log::info('Irewardify order created, vouchers will be fetched separately', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'order_id'          => $transactionId,
        ]);
    } elseif ($this->supplier->slug === 'tikkery') {
        $isCompleted = (bool) ($externalOrderResponse['isCompleted'] ?? false);
        if ($isCompleted && ! empty($externalOrderResponse['codes'])) {
            $tikkeryVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
            $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        } else {
            Log::info('Tikkery order is pending, vouchers will be fetched separately', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'transaction_id'    => $transactionId,
            ]);
        }
    }

    $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
}
```

### Migration path for remaining suppliers

As each supplier's `OrderHandler` class is implemented:

1. Create `app/Suppliers/{Supplier}OrderHandler.php` (following the Ezcards pattern).
2. Add `protected function createXxxDriver()` in `SupplierOrderManager`.
3. Add the slug(s) to `getRegisteredSlugs()`.
4. Remove the corresponding `elseif` branch from the legacy section of the job.

Once all suppliers are migrated the entire legacy block and `PurchaseOrderPlacementService` can
be deleted.

> This change is **non-breaking**: unregistered suppliers continue to flow through the legacy
> path exactly as before.

---

## Step 8 — Tests to Write

### Unit: `tests/Unit/Suppliers/EzcardsOrderHandlerTest.php`

| Test case | Assertion |
|---|---|
| `test_place_order_returns_processing_on_success` | Returns `SupplierOrderResult::processing()`; `transaction_id` set on model |
| `test_place_order_sets_status_to_processing` | `purchase_order_suppliers.status = processing` after call |
| `test_place_order_stores_transaction_id` | `purchase_order_suppliers.transaction_id` matches API response |
| `test_place_order_throws_on_api_failure` | Throws `SupplierOrderException`; status set to `failed` |
| `test_place_order_builds_correct_payload` | `Orders` mock receives expected `sku` / `quantity` / `payWithCurrency` |

Example scaffold:

```php
<?php

namespace Tests\Unit\Suppliers;

use Tests\TestCase;
use App\Suppliers\EzcardsOrderHandler;
use App\Clients\Ezcards\Orders;
use App\DTOs\SupplierOrderResult;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Exceptions\SupplierOrderException;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class EzcardsOrderHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_order_returns_processing_on_success(): void
    {
        $ordersClient = Mockery::mock(Orders::class);
        $ordersClient->shouldReceive('placeOrder')
            ->once()
            ->andReturn(['transactionId' => 'TXN-001', 'data' => []]);

        $handler = new EzcardsOrderHandler($ordersClient);

        // Create the necessary DB records via factories...
        $purchaseOrderSupplier = PurchaseOrderSupplier::factory()
            ->for(\App\Models\PurchaseOrder::factory()->create(['order_number' => 'PO-001', 'currency' => 'USD']))
            ->for(\App\Models\Supplier::factory()->create(['slug' => 'ez_cards']))
            ->create(['status' => PurchaseOrderSupplierStatus::PROCESSING->value]);

        $result = $handler->placeOrder($purchaseOrderSupplier);

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertTrue($result->isProcessing());
        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id'             => $purchaseOrderSupplier->id,
            'transaction_id' => 'TXN-001',
            'status'         => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
    }

    public function test_place_order_throws_on_api_failure(): void
    {
        $ordersClient = Mockery::mock(Orders::class);
        $ordersClient->shouldReceive('placeOrder')
            ->once()
            ->andThrow(new \RuntimeException('API timeout'));

        $handler = new EzcardsOrderHandler($ordersClient);

        $purchaseOrderSupplier = PurchaseOrderSupplier::factory()
            ->for(\App\Models\PurchaseOrder::factory()->create())
            ->for(\App\Models\Supplier::factory()->create(['slug' => 'ez_cards']))
            ->create();

        $this->expectException(SupplierOrderException::class);
        $handler->placeOrder($purchaseOrderSupplier);

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'id'     => $purchaseOrderSupplier->id,
            'status' => PurchaseOrderSupplierStatus::FAILED->value,
        ]);
    }
}
```

### Feature: `tests/Feature/Managers/SupplierOrderManagerTest.php`

| Test case | Assertion |
|---|---|
| `test_driver_resolves_ezcards_handler` | `$manager->driver('ez_cards')` returns `EzcardsOrderHandler` |
| `test_supports_returns_true_for_ez_cards` | `$manager->supports('ez_cards')` returns `true` |
| `test_supports_returns_false_for_unknown_slug` | `$manager->supports('unknown')` returns `false` |
| `test_ezcards_is_not_in_pollable_drivers` | `getPollableDrivers()` returns empty array |
| `test_ezcards_is_not_in_webhook_drivers` | `getWebhookDrivers()` returns empty array |

---

## Implementation Checklist

- [ ] Create `app/Contracts/Suppliers/SupplierOrderHandlerInterface.php`
- [ ] Create `app/Contracts/Suppliers/PollableSupplierInterface.php`
- [ ] Create `app/Contracts/Suppliers/WebhookSupplierInterface.php`
- [ ] Create `app/DTOs/SupplierOrderResult.php`
- [ ] Create `app/Exceptions/SupplierOrderException.php`
- [ ] Create `app/Suppliers/EzcardsOrderHandler.php`
- [ ] Create `app/Managers/SupplierOrderManager.php` (multi-supplier capable, Ezcards registered)
- [ ] Register `SupplierOrderManager` singleton in `AppServiceProvider::register()`
- [ ] Update `PlaceExternalPurchaseOrderJob::handle()` — manager-first dispatch with legacy fallback
- [ ] Write unit tests in `tests/Unit/Suppliers/EzcardsOrderHandlerTest.php`
- [ ] Write feature tests in `tests/Feature/Managers/SupplierOrderManagerTest.php`
- [ ] Run `./vendor/bin/pint`
- [ ] Run `./vendor/bin/phpstan analyse`
- [ ] Run `./vendor/bin/phpunit`

---

## What Is NOT Changed by This Implementation

- `EzcardsPlaceOrderService` — left intact; used by `PurchaseOrderPlacementService` for the
  non-manager path until all suppliers are migrated.
- `PurchaseOrderPlacementService` — the Ezcards `if` branch is bypassed by the job after Step 7,
  but the class itself is not deleted (other suppliers still rely on it).
- `GetVoucherCodes` action and the voucher-retrieval flow — entirely out of scope; Ezcards voucher
  fetching remains as-is.
- All other supplier handlers (Gift2Games, Giftery, Irewardify, Tikkery) — unaffected; follow the
  same pattern in their own separate implementation documents.
