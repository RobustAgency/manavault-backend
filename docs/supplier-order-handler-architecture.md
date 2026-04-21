# Supplier Order Handler Architecture

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Interface Definitions](#2-interface-definitions)
3. [Manager Design](#3-manager-design)
4. [DTO Specification](#4-dto-specification)
5. [Client Isolation Strategy](#5-client-isolation-strategy)
6. [Adding a New Supplier](#6-adding-a-new-supplier)
7. [Design Decisions](#7-design-decisions)
8. [File Structure Reference](#8-file-structure-reference)

---

## 1. Architecture Overview

### Component Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│                     Calling Layer                               │
│          (Controller / Job / Service)                           │
└────────────────────────┬────────────────────────────────────────┘
                         │  $manager->driver($slug)->placeOrder(...)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│               SupplierOrderManager                              │
│         (extends Illuminate\Support\Manager)                    │
│                                                                 │
│  driver($slug)         ──►  resolves handler by supplier slug   │
│  getPollableDrivers()  ──►  all PollableSupplierInterface impls │
│  getWebhookDrivers()   ──►  all WebhookSupplierInterface impls  │
└────────────────────────┬────────────────────────────────────────┘
                         │  resolves to one of ▼
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
   ┌─────────────┐ ┌──────────────┐ ┌──────────────────────┐
   │ EzcardsOrder│ │Gift2GamesOrder│ │  GifteryOrderHandler │
   │  Handler    │ │   Handler    │ │  (+ others...)       │
   └──────┬──────┘ └──────┬───────┘ └──────────┬───────────┘
          │               │                    │
    implements       implements           implements
   SupplierOrder    SupplierOrder +       SupplierOrder +
   HandlerInterface PollableSupplier      WebhookSupplier
                    Interface             Interface
          │               │                    │
          ▼               ▼                    ▼
   ┌─────────────┐ ┌──────────────┐ ┌──────────────────────┐
   │EzcardsClient│ │Gift2GamesClient│ │   GifteryClient      │
   │(own HTTP    │ │(own HTTP     │ │  (own HTTP client)   │
   │ client)     │ │ client)      │ │                      │
   └─────────────┘ └──────────────┘ └──────────────────────┘
          │               │                    │
          └───────────────┴────────────────────┘
                          │  all return ▼
                 ┌────────────────────┐
                 │  SupplierOrderResult│  (DTO)
                 │  ->status           │
                 │  ->isCompleted()    │
                 │  ->getOrder()       │
                 └────────────────────┘
```

### Fulfillment Strategy per Supplier Type

| Supplier | Implements | Delivery Mode |
|---|---|---|
| Ezcards | `SupplierOrderHandlerInterface` | Synchronous — returns vouchers immediately |
| Gift2Games | `SupplierOrderHandlerInterface` + `PollableSupplierInterface` | Async polling — order placed, then polled |
| Giftery | `SupplierOrderHandlerInterface` + `WebhookSupplierInterface` | Webhook push — supplier calls back when ready |
| Irewardify | `SupplierOrderHandlerInterface` + `PollableSupplierInterface` | Async polling |
| Tikkery | `SupplierOrderHandlerInterface` + `WebhookSupplierInterface` | Webhook push |

> This table is illustrative. The actual fulfillment mode per supplier must be confirmed from each supplier's integration documentation.

---

## 2. Interface Definitions

### 2.1 `SupplierOrderHandlerInterface` — Base Contract

**Namespace:** `App\Contracts\Suppliers`

**Responsibility:** Every supplier handler must implement this interface. It defines the single entry point for placing an order with a supplier.

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
     * Implementations must communicate with the supplier's API
     * to initiate a purchase for the given PurchaseOrderSupplier record.
     *
     * The returned SupplierOrderResult encapsulates:
     *   - The current status (PROCESSING, COMPLETED, or FAILED)
     *   - The hydrated PurchaseOrderSupplier model (if completed synchronously)
     *
     * @param  PurchaseOrderSupplier  $purchaseOrderSupplier  The supplier order record to fulfil
     * @return SupplierOrderResult
     *
     * @throws \App\Exceptions\SupplierOrderException  On unrecoverable API errors
     */
    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
```

**When to implement:** Every supplier handler, without exception.

---

### 2.2 `PollableSupplierInterface` — Polling Contract

**Namespace:** `App\Contracts\Suppliers`

**Responsibility:** Extends the base contract for suppliers that do NOT return fulfilled vouchers immediately. After placing an order, the system must periodically poll the supplier to check for completion.

```php
<?php

namespace App\Contracts\Suppliers;

use App\Models\PurchaseOrderSupplier;
use App\DTOs\SupplierOrderResult;

interface PollableSupplierInterface extends SupplierOrderHandlerInterface
{
    /**
     * Poll the supplier for the status of a previously placed order.
     *
     * Called by a scheduled job (e.g., PollPendingSupplierOrders) at regular
     * intervals for all PurchaseOrderSupplier records with status = PROCESSING.
     *
     * Implementations should:
     *   1. Query the supplier's API using the stored transaction_id
     *   2. Return a SupplierOrderResult reflecting the latest status
     *   3. If completed, populate the result with the hydrated model and voucher data
     *
     * @param  PurchaseOrderSupplier  $purchaseOrderSupplier  The in-progress order to check
     * @return SupplierOrderResult
     *
     * @throws \App\Exceptions\SupplierOrderException  On unrecoverable API errors
     */
    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
```

**When to implement:** Suppliers where `placeOrder()` returns a `transaction_id` but vouchers are delivered asynchronously (e.g., Gift2Games, Irewardify).

---

### 2.3 `WebhookSupplierInterface` — Webhook Push Contract

**Namespace:** `App\Contracts\Suppliers`

**Responsibility:** Extends the base contract for suppliers that push fulfillment notifications back to Manavault via HTTP webhooks, rather than requiring polling.

```php
<?php

namespace App\Contracts\Suppliers;

use App\Models\PurchaseOrderSupplier;
use App\DTOs\SupplierOrderResult;
use Illuminate\Http\Request;

interface WebhookSupplierInterface extends SupplierOrderHandlerInterface
{
    /**
     * Handle an inbound webhook payload from the supplier.
     *
     * Called by a dedicated webhook controller route. The implementation is
     * responsible for:
     *   1. Validating the incoming request signature/token
     *   2. Parsing the supplier-specific payload structure
     *   3. Locating the corresponding PurchaseOrderSupplier by transaction_id
     *   4. Returning a SupplierOrderResult with updated status and order data
     *
     * @param  Request  $request  The raw inbound webhook HTTP request
     * @return SupplierOrderResult
     *
     * @throws \App\Exceptions\SupplierWebhookException  On invalid signature or unknown order
     */
    public function handleWebhook(Request $request): SupplierOrderResult;

    /**
     * Verify the authenticity of the inbound webhook request.
     *
     * Each supplier uses a different mechanism (HMAC signature, shared secret,
     * IP allowlist, etc.). This method must return false for invalid requests.
     *
     * @param  Request  $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool;
}
```

**When to implement:** Suppliers that call a Manavault endpoint upon fulfillment (e.g., Giftery, Tikkery).

---

### Interface Hierarchy Summary

```
SupplierOrderHandlerInterface
    ├── placeOrder()
    │
    ├── PollableSupplierInterface   (+ pollOrder())
    │
    └── WebhookSupplierInterface    (+ handleWebhook(), verifyWebhookSignature())
```

---

## 3. Manager Design

### 3.1 `SupplierOrderManager`

**Namespace:** `App\Managers`  
**Extends:** `Illuminate\Support\Manager`

`Illuminate\Support\Manager` is Laravel's built-in driver-resolution base class (same pattern used by `CacheManager`, `QueueManager`, etc.). It provides `driver($name)` and `getDefaultDriver()` out of the box.

```php
<?php

namespace App\Managers;

use App\Contracts\Suppliers\PollableSupplierInterface;
use App\Contracts\Suppliers\SupplierOrderHandlerInterface;
use App\Contracts\Suppliers\WebhookSupplierInterface;
use App\Suppliers\EzcardsOrderHandler;
use App\Suppliers\Gift2GamesOrderHandler;
use App\Suppliers\GifteryOrderHandler;
use App\Suppliers\IrewardifyOrderHandler;
use App\Suppliers\TikkeryOrderHandler;
use Illuminate\Support\Manager;

class SupplierOrderManager extends Manager
{
    /**
     * Get the default driver name.
     * Not typically used since drivers are always resolved by explicit slug.
     */
    public function getDefaultDriver(): string
    {
        return 'ezcards';
    }

    // ──────────────────────────────────────────────────────────────
    // Driver factory methods — one per supplier slug.
    // Named: create{PascalCaseSlug}Driver()
    // Laravel's Manager resolves these automatically via driver($slug).
    // ──────────────────────────────────────────────────────────────

    protected function createEzcardsDriver(): EzcardsOrderHandler
    {
        return $this->container->make(EzcardsOrderHandler::class);
    }

    protected function createGift2gamesDriver(): Gift2GamesOrderHandler
    {
        return $this->container->make(Gift2GamesOrderHandler::class);
    }

    protected function createGifteryDriver(): GifteryOrderHandler
    {
        return $this->container->make(GifteryOrderHandler::class);
    }

    protected function createIrewardifyDriver(): IrewardifyOrderHandler
    {
        return $this->container->make(IrewardifyOrderHandler::class);
    }

    protected function createTikkeryDriver(): TikkeryOrderHandler
    {
        return $this->container->make(TikkeryOrderHandler::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Filtered driver collections
    // ──────────────────────────────────────────────────────────────

    /**
     * Return all registered driver slugs that implement PollableSupplierInterface.
     *
     * Used by scheduled jobs (e.g., PollPendingSupplierOrders) to determine
     * which suppliers need periodic polling.
     *
     * @return array<string, PollableSupplierInterface>
     */
    public function getPollableDrivers(): array
    {
        return $this->getDriversByInterface(PollableSupplierInterface::class);
    }

    /**
     * Return all registered driver slugs that implement WebhookSupplierInterface.
     *
     * Used to enumerate webhook-capable suppliers, e.g., for documentation
     * or registration of webhook endpoints.
     *
     * @return array<string, WebhookSupplierInterface>
     */
    public function getWebhookDrivers(): array
    {
        return $this->getDriversByInterface(WebhookSupplierInterface::class);
    }

    /**
     * Resolve all known drivers and filter by interface.
     *
     * @param  class-string  $interface
     * @return array<string, SupplierOrderHandlerInterface>
     */
    private function getDriversByInterface(string $interface): array
    {
        $slugs = $this->getRegisteredSlugs();
        $matched = [];

        foreach ($slugs as $slug) {
            $driver = $this->driver($slug);
            if ($driver instanceof $interface) {
                $matched[$slug] = $driver;
            }
        }

        return $matched;
    }

    /**
     * All known supplier slugs. Must be kept in sync with createXxxDriver() methods.
     * Alternatively, this could be driven by a config file.
     *
     * @return string[]
     */
    private function getRegisteredSlugs(): array
    {
        return [
            'ezcards',
            'gift2games',
            'giftery',
            'irewardify',
            'tikkery',
        ];
    }
}
```

### 3.2 Service Provider Registration

Register the manager as a singleton in `AppServiceProvider` (or a dedicated `SupplierServiceProvider`):

```php
// app/Providers/AppServiceProvider.php

use App\Managers\SupplierOrderManager;

public function register(): void
{
    $this->app->singleton(SupplierOrderManager::class, function ($app) {
        return new SupplierOrderManager($app);
    });
}
```

### 3.3 Driver Resolution Strategy

Laravel's `Manager` base class resolves drivers by convention:

1. `driver('gift2games')` is called.
2. Manager transforms the slug: `'gift2games'` → `createGift2gamesDriver()`.
3. The method is called on the first resolution and cached in `$this->drivers[$name]`.

**Slug-to-method mapping:** The slug must match the suffix in `create{Slug}Driver()` with the first character uppercased. For multi-word slugs like `gift2games`, the convention `createGift2gamesDriver()` applies.

### 3.4 Open/Closed Principle

Adding a new supplier requires:
- Adding a `createNewSupplierDriver()` method to `SupplierOrderManager`
- Adding the slug to `getRegisteredSlugs()`
- **No changes to any existing handler or interface**

Existing drivers are completely unaffected.

---

## 4. DTO Specification

### 4.1 `SupplierOrderResult`

**Namespace:** `App\DTOs`

```php
<?php

namespace App\DTOs;

use App\Enums\PurchaseOrderSupplierStatus;
use App\Models\PurchaseOrderSupplier;

final class SupplierOrderResult
{
    /**
     * @param  PurchaseOrderSupplierStatus  $status
     *     The current order status from the supplier's perspective.
     *     - PROCESSING: Order placed, awaiting fulfillment (async suppliers)
     *     - COMPLETED:  Order fulfilled; vouchers/items are available
     *     - FAILED:     Supplier returned an error; order cannot proceed
     *
     * @param  PurchaseOrderSupplier|null  $order
     *     The hydrated PurchaseOrderSupplier model.
     *     Populated only when the order is fulfilled synchronously (COMPLETED).
     *     Null when status is PROCESSING or FAILED.
     */
    public function __construct(
        private readonly PurchaseOrderSupplierStatus $status,
        private readonly ?PurchaseOrderSupplier $order = null,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // Named constructors — preferred over calling new directly
    // ──────────────────────────────────────────────────────────────

    /**
     * Create a result for an order that was fulfilled immediately.
     * Typical for synchronous suppliers (e.g., Ezcards).
     */
    public static function completed(PurchaseOrderSupplier $order): self
    {
        return new self(
            status: PurchaseOrderSupplierStatus::COMPLETED,
            order: $order,
        );
    }

    /**
     * Create a result for an order that has been accepted but not yet fulfilled.
     * Typical for async suppliers requiring polling or webhook callback.
     */
    public static function processing(): self
    {
        return new self(
            status: PurchaseOrderSupplierStatus::PROCESSING,
        );
    }

    /**
     * Create a result for an order that failed at the supplier level.
     */
    public static function failed(): self
    {
        return new self(
            status: PurchaseOrderSupplierStatus::FAILED,
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    public function getStatus(): PurchaseOrderSupplierStatus
    {
        return $this->status;
    }

    /**
     * Returns true only if the order was fulfilled synchronously.
     * Use this to determine whether to await polling/webhook or proceed immediately.
     */
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

    /**
     * Returns the hydrated PurchaseOrderSupplier model.
     * Only populated when isCompleted() === true.
     * Returns null for PROCESSING or FAILED states.
     */
    public function getOrder(): ?PurchaseOrderSupplier
    {
        return $this->order;
    }
}
```

### 4.2 DTO Lifecycle

```
placeOrder() called
        │
        ├── Supplier responds synchronously with vouchers
        │         └──► SupplierOrderResult::completed($hydratedOrder)
        │                       isCompleted() = true
        │                       getOrder()    = PurchaseOrderSupplier (with items)
        │
        ├── Supplier accepts order, fulfilment is async
        │         └──► SupplierOrderResult::processing()
        │                       isCompleted() = false
        │                       getOrder()    = null
        │                       → system stores transaction_id on PurchaseOrderSupplier
        │                       → PollPendingSupplierOrders job calls pollOrder() later
        │                         OR supplier webhook triggers handleWebhook()
        │
        └── Supplier returns error
                  └──► SupplierOrderResult::failed()
                                isCompleted() = false
                                getOrder()    = null
                                → system marks order failed, alerts raised
```

### 4.3 Construction Examples

```php
// Synchronous completion (e.g., inside EzcardsOrderHandler::placeOrder())
$purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
return SupplierOrderResult::completed($purchaseOrderSupplier->fresh());

// Async accepted (e.g., inside Gift2GamesOrderHandler::placeOrder())
$purchaseOrderSupplier->update([
    'transaction_id' => $apiResponse['order_id'],
    'status'         => PurchaseOrderSupplierStatus::PROCESSING->value,
]);
return SupplierOrderResult::processing();

// Failure (e.g., supplier returned 4xx/5xx)
$purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);
return SupplierOrderResult::failed();
```

### 4.4 Caller Usage

```php
$result = $manager->driver($supplier->slug)->placeOrder($purchaseOrderSupplier);

if ($result->isCompleted()) {
    // Vouchers are already attached — notify the customer immediately
    $order = $result->getOrder();
    dispatch(new FulfillSaleOrder($order));
} elseif ($result->isProcessing()) {
    // Nothing to do now — polling job or webhook will complete it
    Log::info("Order {$purchaseOrderSupplier->id} is processing with supplier.");
} else {
    // Handle failure — alert, retry, or escalate
    throw new SupplierOrderException("Order failed for supplier: {$supplier->slug}");
}
```

---

## 5. Client Isolation Strategy

### 5.1 Principle

Each supplier retains its own dedicated HTTP client class. **The client is not part of any shared interface contract.** The `SupplierOrderHandlerInterface` and its extensions know nothing about HTTP clients — this is intentional.

This means:
- Client changes for one supplier have zero impact on other handlers
- Each client can have its own auth strategy, base URL, retry logic, and headers
- Clients extend `BaseApiClient` to share HTTP boilerplate (headers, request wrapping)

### 5.2 Client Structure

All supplier clients extend the existing `App\Clients\BaseApiClient`:

```
app/Clients/
    BaseApiClient.php          ← abstract, handles HTTP boilerplate
    Ezcards/
        EzcardsClient.php
    Gift2Games/
        Gift2GamesClient.php
    Giftery/
        GifteryClient.php
    Irewardify/
        IrewardifyClient.php
    Tikkery/
        TikkeryClient.php
```

### 5.3 Client Injection into Handlers

Clients are injected via constructor. The IoC container resolves them automatically:

```php
<?php

namespace App\Suppliers;

use App\Clients\Gift2Games\Gift2GamesClient;
use App\Contracts\Suppliers\PollableSupplierInterface;
use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

class Gift2GamesOrderHandler implements PollableSupplierInterface
{
    public function __construct(
        private readonly Gift2GamesClient $client,
    ) {}

    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        $response = $this->client->createOrder([/* ... */]);

        $purchaseOrderSupplier->update([
            'transaction_id' => $response['order_id'],
            'status'         => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        return SupplierOrderResult::processing();
    }

    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        $response = $this->client->getOrderStatus($purchaseOrderSupplier->transaction_id);

        if ($response['status'] === 'completed') {
            // attach vouchers to purchase order items...
            $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            return SupplierOrderResult::completed($purchaseOrderSupplier->fresh());
        }

        return SupplierOrderResult::processing();
    }
}
```

### 5.4 Why Clients Are Not in the Interface

If the client were part of the interface contract, all handlers would be forced to accept a common client type — breaking isolation. By keeping the client as a constructor dependency of the concrete handler only:

- The `SupplierOrderManager` resolves handlers via the container, which auto-injects the correct client
- Swapping a client implementation requires zero changes outside the handler's own namespace
- Tests can mock the client independently per handler

---

## 6. Adding a New Supplier

Follow these steps to integrate a new supplier called `acme` (slug: `acme`):

### Step 1 — Create the HTTP Client

```
app/Clients/Acme/AcmeClient.php
```

Extend `BaseApiClient`. Implement `getConfigPrefix()` and `getServiceName()`. Add supplier-specific API methods.

```php
namespace App\Clients\Acme;

use App\Clients\BaseApiClient;

class AcmeClient extends BaseApiClient
{
    protected function getConfigPrefix(): string { return 'services.acme'; }
    protected function getServiceName(): string  { return 'Acme'; }

    public function createOrder(array $payload): array { /* ... */ }
}
```

Add credentials to `config/services.php` and `.env`.

### Step 2 — Determine the Fulfillment Mode

| Mode | Implement |
|---|---|
| Synchronous (immediate vouchers) | `SupplierOrderHandlerInterface` only |
| Async polling | `SupplierOrderHandlerInterface` + `PollableSupplierInterface` |
| Webhook push | `SupplierOrderHandlerInterface` + `WebhookSupplierInterface` |

### Step 3 — Create the Handler

```
app/Suppliers/AcmeOrderHandler.php
```

```php
namespace App\Suppliers;

use App\Clients\Acme\AcmeClient;
use App\Contracts\Suppliers\PollableSupplierInterface; // if polling
use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

class AcmeOrderHandler implements PollableSupplierInterface
{
    public function __construct(
        private readonly AcmeClient $client,
    ) {}

    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        // call $this->client, update model, return SupplierOrderResult::processing()
    }

    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        // call $this->client->getStatus(), return completed or processing
    }
}
```

### Step 4 — Register the Driver in the Manager

In `SupplierOrderManager`, add:

```php
protected function createAcmeDriver(): AcmeOrderHandler
{
    return $this->container->make(AcmeOrderHandler::class);
}
```

And add `'acme'` to `getRegisteredSlugs()`:

```php
private function getRegisteredSlugs(): array
{
    return [
        'ezcards', 'gift2games', 'giftery', 'irewardify', 'tikkery',
        'acme', // ← new
    ];
}
```

### Step 5 — (Webhook only) Register a Webhook Route

If the supplier uses webhooks, add a route in `routes/api.php`:

```php
Route::post('/webhooks/acme', [SupplierWebhookController::class, 'handle'])
    ->name('webhooks.acme');
```

The controller resolves the correct handler via the manager:

```php
$handler = $this->manager->driver('acme'); // implements WebhookSupplierInterface
$result  = $handler->handleWebhook($request);
```

### Step 6 — Add the Supplier Record

Ensure a `suppliers` table row exists with `slug = 'acme'`. This is used by the manager's `driver()` call at runtime.

---

## 7. Design Decisions

### 7.1 Why `Illuminate\Support\Manager`?

Laravel's `Manager` is purpose-built for driver resolution. It provides:
- Built-in driver caching (each driver is instantiated only once)
- A clean `driver($name)` API
- Convention-based method resolution (`create{Name}Driver()`)

The alternative — a plain factory class or a keyed array — requires manual caching and more boilerplate.

### 7.2 Why Interface Segregation (ISP)?

Not all suppliers support polling. Not all support webhooks. Forcing every handler to implement a monolithic `SupplierOrderHandlerInterface` with `pollOrder()` and `handleWebhook()` would require empty/throw implementations for irrelevant methods.

By splitting into three interfaces:
- `getPollableDrivers()` can reliably type-check using `instanceof PollableSupplierInterface`
- Handlers only implement what their supplier actually supports
- Adding a new delivery mode (e.g., email-based) requires a new interface — no changes to existing handlers

### 7.3 Why a Typed DTO over Raw Arrays?

`SupplierOrderResult` enforces at compile-time (via PHPStan level 5) that:
- Status is always a `PurchaseOrderSupplierStatus` enum — no raw strings
- `getOrder()` always returns `?PurchaseOrderSupplier` — no unexpected shapes
- The caller cannot accidentally access `$result['order']` on a failed result

Named constructors (`::completed()`, `::processing()`, `::failed()`) further eliminate incorrect construction.

### 7.4 Why Client Isolation?

Each supplier has a unique API: different auth schemes, base URLs, payload structures, and retry policies. Sharing a single HTTP client would require complex conditional logic. Keeping clients isolated means:
- Changes to Giftery's auth token mechanism only touch `GifteryClient`
- Unit tests for `Gift2GamesOrderHandler` mock `Gift2GamesClient` directly
- No risk of one supplier's configuration bleeding into another

### 7.5 Why Constructor Injection for Clients?

Laravel's IoC container resolves constructor dependencies automatically. Injecting via constructor (rather than method injection or service locator) means:
- Dependencies are declared and visible
- PHPStan can analyse types correctly
- The handler is fully testable with a mocked client

---

## 8. File Structure Reference

```
app/
├── Contracts/
│   └── Suppliers/
│       ├── SupplierOrderHandlerInterface.php
│       ├── PollableSupplierInterface.php
│       └── WebhookSupplierInterface.php
│
├── DTOs/
│   └── SupplierOrderResult.php
│
├── Managers/
│   └── SupplierOrderManager.php
│
├── Suppliers/
│   ├── EzcardsOrderHandler.php
│   ├── Gift2GamesOrderHandler.php
│   ├── GifteryOrderHandler.php
│   ├── IrewardifyOrderHandler.php
│   └── TikkeryOrderHandler.php
│
├── Clients/
│   ├── BaseApiClient.php          (existing)
│   ├── Ezcards/
│   │   └── EzcardsClient.php      (existing)
│   ├── Gift2Games/
│   │   └── Gift2GamesClient.php   (existing)
│   ├── Giftery/
│   │   └── GifteryClient.php      (existing)
│   ├── Irewardify/
│   │   └── IrewardifyClient.php   (existing)
│   └── Tikkery/
│       └── TikkeryClient.php      (existing)
│
└── Providers/
    └── AppServiceProvider.php     (register SupplierOrderManager singleton)
```
