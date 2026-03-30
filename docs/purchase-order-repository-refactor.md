# Purchase Order Repository Refactor — Implementation Document

## Overview

The `PurchaseOrderRepository` currently violates the repository pattern by embedding significant business logic directly inside it. A repository's sole responsibility is to **interact with the database** — reading and writing Eloquent models. All orchestration, external API calls, price calculations, and supplier-specific branching must move to a dedicated **service layer**.

This document outlines the required refactoring before implementing the auto-purchase-order feature described in `auto-purchase-order-on-sale-order.md`.

---

## Problem: What Business Logic Lives in the Repository Today?

### `PurchaseOrderRepository::createPurchaseOrder()`

| Concern | Lines of Responsibility | Belongs In |
|---------|------------------------|------------|
| Grouping items by supplier | Delegates to `GroupBySupplierIdService` — but this call itself is orchestration | Service layer |
| `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()` | Transaction management | Service layer |
| Order number generation (`generateOrderNumber()`) | Business rule | Service layer |
| Iterating suppliers and dispatching to `processPurchaseOrderItems()` | Orchestration loop | Service layer |
| Updating overall PO status after all suppliers are processed | Delegates to `PurchaseOrderStatusService` — but the call is orchestration | Service layer |

### `PurchaseOrderRepository::processPurchaseOrderItems()`

| Concern | Belongs In |
|---------|-----------|
| Loading `DigitalProduct` models and computing `unit_cost` / `subtotal` | Service layer (price calculation) |
| Creating `PurchaseOrderSupplier` record | Repository (DB write — acceptable) |
| Calling `placeExternalOrder()` for external suppliers | Service layer (external API orchestration) |
| Catching external API exceptions, updating supplier status to `FAILED` | Service layer (error handling) |
| Logging external order placement success/failure | Service layer |
| Calling `gift2GamesVoucherService->storeVouchers()` | Service layer |
| Updating `PurchaseOrderSupplier` status to `COMPLETED` | Repository (DB write — acceptable) |
| Logging the Ezcards deferred-voucher note | Service layer |
| Incrementally updating `total_price` on the `PurchaseOrder` | Service layer |

### Private Helpers

| Method | Belongs In |
|--------|-----------|
| `generateOrderNumber()` | Service layer |
| `isExternalSupplier()` | Can stay as a helper on a service, or use `SupplierType` enum comparison |
| `isGift2GamesSupplier()` | Service layer (supplier resolution logic) |
| `placeExternalOrder()` — dispatches to Ezcards or Gift2Games based on slug | Service layer (`PurchaseOrderPlacementService` or similar) |

### Injected Dependencies That Confirm Misplacement

The repository currently injects **five** dependencies:

```php
public function __construct(
    private EzcardsPlaceOrderService $ezcardPlaceOrderService,       // external API
    private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService, // external API
    private Gift2GamesVoucherService $gift2GamesVoucherService,       // voucher storage
    private GroupBySupplierIdService $groupBySupplierIdService,       // data transformation
    private PurchaseOrderStatusService $purchaseOrderStatusService,   // status orchestration
) {}
```

A repository should have **zero** external-API or orchestration dependencies. Its constructor should inject nothing, or at most a query-builder / database abstraction.

---

## Proposed Architecture

```
PurchaseOrderController
        │
        ▼
PurchaseOrderService          ← NEW: owns all business logic & transaction management
        │
        ├─ GroupBySupplierIdService      (already exists — stays as-is)
        ├─ PurchaseOrderPlacementService ← NEW: dispatches to the right external supplier
        │       ├─ EzcardsPlaceOrderService     (already exists)
        │       └─ Gift2GamesPlaceOrderService  (already exists)
        ├─ Gift2GamesVoucherService      (already exists — stays as-is)
        ├─ PurchaseOrderStatusService    (already exists — stays as-is)
        └─ PurchaseOrderRepository       ← THIN: only DB reads/writes
```

---

## Detailed Changes

### 1. Create `PurchaseOrderService` (New)

**Location:** `app/Services/PurchaseOrderService.php`

**Responsibility:** Owns the full lifecycle of creating a purchase order — transaction management, price calculation, supplier orchestration, and status updates.

**Constructor:**
```php
public function __construct(
    private GroupBySupplierIdService $groupBySupplierIdService,
    private PurchaseOrderPlacementService $purchaseOrderPlacementService,
    private Gift2GamesVoucherService $gift2GamesVoucherService,
    private PurchaseOrderStatusService $purchaseOrderStatusService,
    private PurchaseOrderRepository $purchaseOrderRepository,
) {}
```

**Methods to extract from the repository:**

#### `createPurchaseOrder(array $data): PurchaseOrder`

Move the entire body of the current `PurchaseOrderRepository::createPurchaseOrder()` here, including:
- `GroupBySupplierIdService::groupBySupplierId()` call
- `generateOrderNumber()` call
- `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`
- The supplier iteration loop
- `PurchaseOrderStatusService::updateStatus()` call

The repository call becomes a single thin write:
```php
$purchaseOrder = $this->purchaseOrderRepository->createPurchaseOrder([
    'total_price'  => 0,
    'order_number' => $orderNumber,
    'status'       => PurchaseOrderStatus::PROCESSING->value,
    'currency'     => $currency,
]);
```

#### `processPurchaseOrderItems(PurchaseOrder $purchaseOrder, Supplier $supplier, array $items, string $orderNumber, string $currency): void`

Move the entire body of `PurchaseOrderRepository::processPurchaseOrderItems()` here, including:
- Digital product loading and price calculation
- `PurchaseOrderPlacementService::placeOrder()` call for external suppliers
- Exception handling and `FAILED` status update
- Logging
- `Gift2GamesVoucherService::storeVouchers()` call
- `PurchaseOrderSupplier` status update to `COMPLETED`
- `PurchaseOrder::total_price` update

#### `generateOrderNumber(): string` (private)

Move from the repository unchanged.

---

### 2. Create `PurchaseOrderPlacementService` (New)

**Location:** `app/Services/PurchaseOrder/PurchaseOrderPlacementService.php`

**Responsibility:** Resolves which external API client to use based on the supplier and dispatches the order. Extracts the `placeExternalOrder()` / `isGift2GamesSupplier()` logic currently buried in the repository.

**Constructor:**
```php
public function __construct(
    private EzcardsPlaceOrderService $ezcardsPlaceOrderService,
    private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService,
) {}
```

**Methods extracted from the repository:**

#### `placeOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency): array`

```php
public function placeOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency): array
{
    if ($supplier->slug === 'ez_cards') {
        return $this->ezcardsPlaceOrderService->placeOrder($orderItems, $orderNumber, $currency);
    }

    if ($this->isGift2GamesSupplier($supplier)) {
        return $this->gift2GamesPlaceOrderService->placeOrder($orderItems, $orderNumber, $supplier->slug);
    }

    throw new \RuntimeException("Unknown external supplier: {$supplier->slug}");
}
```

#### `isGift2GamesSupplier(Supplier $supplier): bool` (private)

Move from the repository unchanged.

> **Note:** `isExternalSupplier()` should be removed entirely from both the repository and this service. The check should use `$supplier->type === SupplierType::EXTERNAL->value` (or `=== SupplierType::EXTERNAL`) inline or via the `SupplierType` enum, which is already defined in the codebase.

---

### 3. Refactor `PurchaseOrderRepository` — Make It Thin

After moving all business logic to `PurchaseOrderService`, the repository becomes a pure database-access class.

**Remove from the repository:**
- All five constructor dependencies (`EzcardsPlaceOrderService`, `Gift2GamesPlaceOrderService`, `Gift2GamesVoucherService`, `GroupBySupplierIdService`, `PurchaseOrderStatusService`)
- `createPurchaseOrder()` orchestration body (replace with a thin `create()` call)
- `processPurchaseOrderItems()` — deleted entirely
- `generateOrderNumber()` — moved to `PurchaseOrderService`
- `isExternalSupplier()` — deleted (use enum comparison at call site)
- `isGift2GamesSupplier()` — moved to `PurchaseOrderPlacementService`
- `placeExternalOrder()` — moved to `PurchaseOrderPlacementService`

**What remains in the repository:**

```php
class PurchaseOrderRepository
{
    /**
     * Get paginated purchase orders filtered by the provided criteria.
     */
    public function getFilteredPurchaseOrders(array $filters = []): LengthAwarePaginator

    /**
     * Persist a new PurchaseOrder record to the database.
     */
    public function createPurchaseOrder(array $attributes): PurchaseOrder

    /**
     * Persist a new PurchaseOrderSupplier record to the database.
     */
    public function createPurchaseOrderSupplier(array $attributes): PurchaseOrderSupplier

    /**
     * Persist a new PurchaseOrderItem record to the database.
     */
    public function createPurchaseOrderItem(array $attributes): PurchaseOrderItem

    /**
     * Find a PurchaseOrder by ID, eager-loading items.
     */
    public function getPurchaseOrderById(int $id): PurchaseOrder
}
```

> Each of these methods does exactly one thing: query or write the database. No loops, no conditionals, no external calls.

---

### 4. Update `PurchaseOrderController` — Call the Service, Not the Repository

The controller currently calls `$this->repository->createPurchaseOrder()` and `$this->repository->getFilteredPurchaseOrders()` directly. After the refactor:

- **`store()`** → delegates to `PurchaseOrderService::createPurchaseOrder()`
- **`index()`** → may continue calling `PurchaseOrderRepository::getFilteredPurchaseOrders()` directly (read-only queries from the controller are acceptable), or go through the service if additional filtering logic exists

**Updated constructor:**
```php
public function __construct(
    private PurchaseOrderService $purchaseOrderService,
    private PurchaseOrderRepository $purchaseOrderRepository,  // for read-only queries
    private EzcardsVoucherCodeService $ezcardsVoucherCodeService,
) {}
```

---

### 5. Update Tests

The existing `PurchaseOrderRepositoryTest` tests business behaviour (external API calls, voucher creation, supplier status transitions) that will move to the service layer. These tests must be moved and adapted accordingly.

| Current Test | Moves To |
|-------------|---------|
| `test_create_purchase_order_with_gift2games_supplier` | `Tests\Feature\Services\PurchaseOrderServiceTest` |
| `test_create_purchase_order_with_ezcards_supplier` | `Tests\Feature\Services\PurchaseOrderServiceTest` |
| `test_create_purchase_order_with_internal_supplier` | `Tests\Feature\Services\PurchaseOrderServiceTest` |
| `test_create_purchase_order_with_multiple_items` | `Tests\Feature\Services\PurchaseOrderServiceTest` |
| `test_get_paginated_purchase_orders` | Stays in `PurchaseOrderRepositoryTest` — it is a true DB-layer test |
| `test_get_paginated_purchase_orders_with_custom_per_page` | Stays in `PurchaseOrderRepositoryTest` — it is a true DB-layer test |

New unit tests should be added for `PurchaseOrderPlacementService` to verify correct supplier routing.

---

## Affected Files Summary

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Services/PurchaseOrderService.php` | **Create** | Owns all PO creation orchestration, transaction management, and price calculation |
| `app/Services/PurchaseOrder/PurchaseOrderPlacementService.php` | **Create** | Resolves and dispatches to the correct external supplier API |
| `app/Repositories/PurchaseOrderRepository.php` | **Modify** | Strip down to pure DB reads/writes; remove all injected service dependencies |
| `app/Http/Controllers/Admin/PurchaseOrderController.php` | **Modify** | Inject `PurchaseOrderService`; route `store()` through the service |
| `tests/Feature/Repositories/PurchaseOrderRepositoryTest.php` | **Modify** | Remove business-logic tests; keep only DB-query tests |
| `tests/Feature/Services/PurchaseOrderServiceTest.php` | **Create** | Move business-logic tests here; add service-level coverage |
| `tests/Unit/Services/PurchaseOrderPlacementServiceTest.php` | **Create** | Unit tests for supplier routing logic |

---

## Relationship to the Auto-PO Feature

The `auto-purchase-order-on-sale-order.md` document proposes adding `createPurchaseOrderForDigitalProduct()` to `PurchaseOrderRepository`. After this refactor, that method **belongs on `PurchaseOrderService`** instead, since it involves orchestration (calling `GroupBySupplierIdService`, invoking external suppliers, storing vouchers). The repository method it ultimately calls will be the thin `createPurchaseOrder(array $attributes): PurchaseOrder` described above.

The `AutoPurchaseOrderService` described in the auto-PO document should therefore inject **`PurchaseOrderService`**, not `PurchaseOrderRepository`.

---

## Implementation Checklist

- [ ] Create `app/Services/PurchaseOrderService.php` with `createPurchaseOrder()` and `processSupplierItems()` logic
- [ ] Create `app/Services/PurchaseOrder/PurchaseOrderPlacementService.php` with `placeOrder()` and supplier-resolution logic
- [ ] Strip `PurchaseOrderRepository` down to DB-only methods; remove all service dependencies
- [ ] Update `PurchaseOrderController` to inject and call `PurchaseOrderService`
- [ ] Move business-logic tests from `PurchaseOrderRepositoryTest` to `PurchaseOrderServiceTest`
- [ ] Add unit tests for `PurchaseOrderPlacementService`
- [ ] Update the auto-PO implementation document to reflect that `AutoPurchaseOrderService` injects `PurchaseOrderService`, not `PurchaseOrderRepository`
- [ ] Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/phpunit` before merging
