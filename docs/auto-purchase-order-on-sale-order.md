# Auto Purchase Order on Sale Order — Implementation Document

## Overview

When creating a sale order the system must handle four distinct inventory scenarios gracefully, rather than rejecting the order whenever stock is insufficient:

1. **Internal supplier, sufficient stock** → allocate vouchers immediately, complete the order.
2. **Internal supplier, insufficient stock** → allocate whatever vouchers are available, leave the order in `processing` and fulfil the remainder when new stock arrives.
3. **External supplier, sufficient stock** → allocate vouchers immediately (existing behaviour, no change required).
4. **External supplier, insufficient stock** → auto-create a purchase order for the shortfall; for Gift2Games vouchers are synchronous so the order can complete in the same request; for Ezcards vouchers are asynchronous so the order is left in `processing` and fulfilled later.

A background event/listener pair (`NewVouchersAvailable` → `FulfillPendingSaleOrders`) re-processes all `processing` sale orders whenever new voucher stock lands in the system — regardless of how it arrived (manual import, Gift2Games sync, or the Ezcards polling webhook).

---

## Background & Context

### Current Flow (Pre-Feature)

```
createOrder()
  └─ validateProductsAndDigitalStock()   ← throws if stock < requested qty (any supplier)
  └─ create SaleOrder + SaleOrderItems
  └─ allocateDigitalProducts()           ← allocates existing vouchers; throws if not enough
  └─ SaleOrder status → COMPLETED
  └─ SaleOrderCompleted event dispatched
```

Any shortfall — internal or external — causes an immediate rejection.

### New Flow (Post-Feature)

```
createOrder()
  └─ validateProductsAndDigitalStock()   ← throws only if truly unrecoverable (see rules below)
  └─ triggerAutoPurchaseOrdersIfNeeded() ← NEW: POs for external-supplier shortfalls only
  └─ create SaleOrder + SaleOrderItems
  └─ allocateDigitalProducts()           ← partial allocation allowed
  └─ if fully allocated  → SaleOrder status → COMPLETED → SaleOrderCompleted event
  └─ if partially allocated → SaleOrder status → PROCESSING (fulfilled later by listener)
```

---

## Affected Files

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Services/SaleOrderService.php` | **Modify** | Inject `AutoPurchaseOrderService`; rework validation, allocation, and status logic |
| `app/Services/AutoPurchaseOrderService.php` | **Create** | Encapsulates all auto-PO shortfall logic |
| `app/Services/PurchaseOrderService.php` | **Modify** | Add `createPurchaseOrderForDigitalProduct()` convenience method |
| `app/Events/NewVouchersAvailable.php` | **Create** | Fired whenever new vouchers are persisted to the database |
| `app/Listeners/FulfillPendingSaleOrders.php` | **Create** | Queued listener — re-attempts allocation for all `processing` sale orders |
| `app/Enums/SaleOrder/Status.php` | **Modify** | Add `PROCESSING = 'processing'` case |

> **Note:** `PurchaseOrderRepository` is a thin DB-access class — no new logic goes there. All PO creation orchestration lives in `PurchaseOrderService`. See `docs/purchase-order-repository-refactor.md` for the completed refactor.

---

## Detailed Design

### 1. `SaleOrder\Status` Enum — Add `PROCESSING`

```php
enum Status: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';   // NEW — partially or fully unfulfilled, awaiting stock
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';
}
```

> A `PROCESSING` sale order has had its items and price committed to the DB, partial voucher allocation may have occurred, but not all requested vouchers have been allocated yet.

---

### 2. `AutoPurchaseOrderService` (New)

**Location:** `app/Services/AutoPurchaseOrderService.php`

**Responsibility:** For a given product + shortfall quantity, determine which digital products are sourced from an eligible external supplier and create purchase orders for them.

**Constructor:**
```php
public function __construct(
    private VoucherAllocationService $voucherAllocationService,
    private PurchaseOrderService $purchaseOrderService,
) {}
```

> **Why `PurchaseOrderService` and not `PurchaseOrderRepository`?** The repository is a thin DB-access class with zero orchestration. All PO creation — transactions, external API dispatch, voucher storage, status updates — lives in `PurchaseOrderService`.

**Key method:**
```php
/**
 * Create purchase orders to cover the shortfall for a product from eligible external suppliers.
 * Returns true if at least one external PO was dispatched, false if no eligible supplier exists.
 */
public function handleShortfall(Product $product, int $shortfall): bool
```

**Logic:**
1. Load the product's digital products ordered by `fulfillment_mode` (`PRICE` → `cost_price ASC`, `MANUAL` → pivot `priority ASC`).
2. For each digital product:
   - Skip if `supplier->type !== SupplierType::EXTERNAL`.
   - Skip Ezcards (`supplier->slug === 'ez_cards'`) — see [Ezcards Edge Case](#ezcards-edge-case) below.
   - Call `PurchaseOrderService::createPurchaseOrderForDigitalProduct($digitalProduct, $shortfall)`.
   - Return `true` after the first successful dispatch (one digital product covers the shortfall).
3. If no eligible external supplier was found, return `false`.

---

### 3. `PurchaseOrderService` — New Method

**Method signature:**
```php
public function createPurchaseOrderForDigitalProduct(
    DigitalProduct $digitalProduct,
    int $quantity
): PurchaseOrder
```

**Logic:** Convenience wrapper that constructs the `items` payload and delegates entirely to `createPurchaseOrder()`:

```php
public function createPurchaseOrderForDigitalProduct(
    DigitalProduct $digitalProduct,
    int $quantity
): PurchaseOrder {
    return $this->createPurchaseOrder([
        'items' => [
            [
                'supplier_id'        => $digitalProduct->supplier_id,
                'digital_product_id' => $digitalProduct->id,
                'quantity'           => $quantity,
            ],
        ],
    ]);
}
```

The existing `createPurchaseOrder()` handles: `GroupBySupplierIdService`, transaction management, `PurchaseOrderPlacementService::placeOrder()`, Gift2Games voucher storage, thin `PurchaseOrderRepository` writes, and `PurchaseOrderStatusService::updateStatus()`.

> **Transaction note:** `createPurchaseOrder()` opens its own `DB::beginTransaction()`. Since it is called from inside `SaleOrderService::createOrder()`'s outer transaction, these are nested savepoints in MySQL/PostgreSQL — if the outer transaction rolls back, the inner writes are rolled back too.

---

### 4. `SaleOrderService` — Modifications

#### 4a. Constructor

```php
public function __construct(
    private ProductRepository $productRepository,
    private SaleOrderRepository $saleOrderRepository,
    private VoucherAllocationService $voucherAllocationService,
    private AutoPurchaseOrderService $autoPurchaseOrderService,  // NEW
) {}
```

#### 4b. `validateProductsAndDigitalStock()` — Soften Validation

The current implementation throws on any shortfall. The new rules are:

| Scenario | Behaviour |
|----------|-----------|
| Product has no digital products | ❌ Throw — cannot fulfil |
| Shortfall exists, **no** external supplier | ✅ Allow — will partially allocate and leave `PROCESSING` |
| Shortfall exists, **eligible** external supplier (Gift2Games) | ✅ Allow — auto-PO will cover it |
| Shortfall exists, **only** Ezcards external supplier | ✅ Allow — auto-PO dispatched, order stays `PROCESSING` until vouchers arrive asynchronously |

```php
private function validateItemInventory(Product $product, int $quantity): void
{
    if ($product->digitalProducts->isEmpty()) {
        throw new \Exception("Product {$product->name} has no digital products assigned.");
    }
    // All other shortfall scenarios are now recoverable — no throw here.
}
```

#### 4c. `createOrder()` — Full Revised Flow

```php
public function createOrder(array $data): SaleOrder
{
    DB::beginTransaction();
    try {
        // 1. Load relations and validate only unrecoverable cases
        $this->validateProductsAndDigitalStock($data['items']);

        // 2. Trigger auto-POs for external-supplier shortfalls (Gift2Games synchronous)
        $this->triggerAutoPurchaseOrdersIfNeeded($data['items']);

        // 3. Create sale order header (PROCESSING by default until fully allocated)
        $saleOrder = $this->saleOrderRepository->createSaleOrder([
            'order_number' => $data['order_number'],
            'source'       => SaleOrder::MANASTORE,
            'total_price'  => 0,
            'status'       => Status::PROCESSING->value,
        ]);

        $totalPrice = 0;
        $fullyAllocated = true;

        // 4. Create items and attempt allocation
        foreach ($data['items'] as $itemData) {
            $product  = $this->productRepository->getProductById($itemData['product_id']);
            $quantity = $itemData['quantity'];

            $item = $saleOrder->items()->create([
                'product_id' => $product->id,
                'quantity'   => $quantity,
                'unit_price' => $product->selling_price,
                'subtotal'   => $quantity * $product->selling_price,
            ]);

            $allocated = $this->allocateDigitalProducts($item, $product, $quantity);

            if ($allocated < $quantity) {
                $fullyAllocated = false;
            }

            $totalPrice += $item->subtotal;
        }

        // 5. Finalise status
        $finalStatus = $fullyAllocated ? Status::COMPLETED->value : Status::PROCESSING->value;

        $saleOrder->update([
            'total_price' => $totalPrice,
            'status'      => $finalStatus,
        ]);

        DB::commit();

        // 6. Dispatch events outside the transaction
        if ($fullyAllocated) {
            event(new SaleOrderCompleted($saleOrder));
        }

        return $saleOrder->load(['items.digitalProducts']);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

#### 4d. `triggerAutoPurchaseOrdersIfNeeded()` (private)

Only runs for items where available stock is less than requested **and** the product has an eligible external supplier (Gift2Games). Ezcards is also dispatched here — its order is placed but vouchers will arrive asynchronously.

```php
private function triggerAutoPurchaseOrdersIfNeeded(array $items): void
{
    foreach ($items as $itemData) {
        $product  = $this->productRepository->getProductById($itemData['product_id']);
        $quantity = $itemData['quantity'];

        $totalAvailable = 0;
        foreach ($product->digitalProducts as $dp) {
            $totalAvailable += $this->voucherAllocationService->getAvailableQuantity($dp->id);
        }

        if ($totalAvailable < $quantity) {
            $shortfall = $quantity - $totalAvailable;
            // Returns false only when no eligible external supplier exists — that is fine,
            // the order will stay PROCESSING until internal stock replenishment.
            $this->autoPurchaseOrderService->handleShortfall($product, $shortfall);
        }
    }
}
```

#### 4e. `allocateDigitalProducts()` — Return Allocated Count (modified signature)

The method must now return how many vouchers were actually allocated instead of throwing on partial fulfilment:

```php
/**
 * Allocate as many vouchers as possible for the item.
 * Returns the number of vouchers actually allocated (may be < $quantity).
 */
private function allocateDigitalProducts(SaleOrderItem $item, Product $product, int $quantity): int
```

- Remove the final `if ($remaining > 0) { throw ... }` block.
- Change the return type from `void` to `int`.
- Return `$quantity - $remaining` at the end.

---

### 5. `NewVouchersAvailable` Event (New)

**Location:** `app/Events/NewVouchersAvailable.php`

**Fired by:** Any service that persists new, unallocated vouchers — specifically:
- `Gift2GamesVoucherService::storeVouchers()` — after Gift2Games auto-PO response
- Voucher import service — after a bulk CSV import is processed
- Ezcards voucher-code poller (`EzcardsVoucherCodeService`) — after fetching codes from the API

```php
class NewVouchersAvailable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $digitalProductIds  IDs of digital products that received new vouchers
     */
    public function __construct(
        public readonly array $digitalProductIds,
    ) {}
}
```

---

### 6. `FulfillPendingSaleOrders` Listener (New)

**Location:** `app/Listeners/FulfillPendingSaleOrders.php`

**Implements:** `ShouldQueue` — runs asynchronously so it does not block the caller.

**Triggered by:** `NewVouchersAvailable`

**Logic:**
1. Find all `SaleOrder` records with `status = PROCESSING`.
2. For each such order, for each item, compute how many vouchers have already been allocated vs. how many are still needed.
3. Attempt to allocate the remaining vouchers using `VoucherAllocationService`.
4. If all items in the order are now fully allocated:
   - Update `SaleOrder.status → COMPLETED`.
   - Fire `SaleOrderCompleted` so `SyncProductsOnSaleOrderCompletion` syncs stock to Manastore.
5. If still partially unfulfilled, leave the order in `PROCESSING` — it will be retried on the next `NewVouchersAvailable` event.

```php
class FulfillPendingSaleOrders implements ShouldQueue
{
    public function __construct(
        private VoucherAllocationService $voucherAllocationService,
        private SaleOrderRepository $saleOrderRepository,
    ) {}

    public function handle(NewVouchersAvailable $event): void
    {
        $pendingOrders = $this->saleOrderRepository->getPendingSaleOrders();

        foreach ($pendingOrders as $saleOrder) {
            $this->tryFulfil($saleOrder);
        }
    }

    private function tryFulfil(SaleOrder $saleOrder): void
    {
        DB::beginTransaction();
        try {
            $fullyAllocated = true;

            foreach ($saleOrder->items as $item) {
                $alreadyAllocated = $item->digitalProducts()->count();
                $remaining        = $item->quantity - $alreadyAllocated;

                if ($remaining <= 0) {
                    continue;
                }

                $product   = $item->product;
                $allocated = $this->allocateDigitalProducts($item, $product, $remaining);

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                }
            }

            if ($fullyAllocated) {
                $saleOrder->update(['status' => Status::COMPLETED->value]);
                DB::commit();
                event(new SaleOrderCompleted($saleOrder));
            } else {
                DB::rollBack(); // Do not partially persist this retry attempt
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FulfillPendingSaleOrders: failed to fulfil order', [
                'sale_order_id' => $saleOrder->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
```

> **Note:** `SaleOrderRepository::getPendingSaleOrders()` is a new read-only method returning all orders with `status = 'processing'`, eager-loading `items.product.digitalProducts`.

---

### 7. Event Registration

Register the new listener pair in `app/Providers/AppServiceProvider.php` boot method (Laravel 12 auto-discovery via `#[AsEventListener]` attribute is also acceptable):

```php
Event::listen(
    NewVouchersAvailable::class,
    FulfillPendingSaleOrders::class,
);
```

### 8. Fire `NewVouchersAvailable` at Every Voucher Ingestion Point

| Location | Where to fire |
|----------|--------------|
| `Gift2GamesVoucherService::storeVouchers()` | After bulk-inserting Gift2Games vouchers |
| Voucher CSV import service | After the import transaction commits |
| `EzcardsVoucherCodeService` | After fetching and persisting Ezcards codes |

```php
event(new NewVouchersAvailable($digitalProductIds));
```

---

## Data Flow Diagram

```
createOrder(items)
    │
    ├─ validateProductsAndDigitalStock()
    │       └─ for each item:
    │               └─ if no digital products → THROW (unrecoverable)
    │               └─ all shortfalls → PASS (recoverable)
    │
    ├─ triggerAutoPurchaseOrdersIfNeeded()
    │       └─ for each item where available < requested:
    │               └─ AutoPurchaseOrderService::handleShortfall(product, shortfall)
    │                       └─ for each digital product with eligible external supplier:
    │                               └─ PurchaseOrderService::createPurchaseOrderForDigitalProduct()
    │                                       ├─ PurchaseOrderPlacementService::placeOrder()
    │                                       │       ├─ Gift2GamesPlaceOrderService → vouchers returned synchronously
    │                                       │       └─ EzcardsPlaceOrderService   → vouchers arrive asynchronously
    │                                       ├─ (Gift2Games) Gift2GamesVoucherService::storeVouchers()
    │                                       │       └─ event(NewVouchersAvailable)  ← triggers background fulfil
    │                                       └─ PurchaseOrderRepository thin writes
    │
    ├─ createSaleOrder() [status = PROCESSING]
    ├─ for each item:
    │       ├─ create SaleOrderItem
    │       └─ allocateDigitalProducts() ← partial allocation; returns count allocated
    │
    ├─ if fully allocated  → status = COMPLETED → event(SaleOrderCompleted)
    └─ if partially allocated → status = PROCESSING (listener will complete later)
                                    │
              ┌─────────────────────┘
              │  (later, when new vouchers arrive)
              ▼
    NewVouchersAvailable event fired
              │
              ▼
    FulfillPendingSaleOrders listener (queued)
              └─ for each PROCESSING sale order:
                      └─ re-attempt allocateDigitalProducts() for remaining qty
                              ├─ if now fully allocated → COMPLETED → SaleOrderCompleted
                              └─ if still short → stays PROCESSING (retry on next event)
```

---

## Supplier-Specific Behaviour

| Supplier | Voucher Timing | Allocation Path |
|----------|---------------|-----------------|
| **Internal** | Pre-stocked — vouchers already in DB | Immediate if stock ≥ qty; partial + `PROCESSING` if stock < qty |
| **Gift2Games** | Synchronous — returned in API response, stored before `allocateDigitalProducts()` runs | Immediate — order completes in same request |
| **Ezcards** | Asynchronous — fetched later by `EzcardsVoucherCodeService` poller | Auto-PO dispatched; order stays `PROCESSING`; `FulfillPendingSaleOrders` completes it when codes arrive |

---

## Ezcards Edge Case

Ezcards vouchers are **not** returned in the purchase order API response. `EzcardsVoucherCodeService` polls later to fetch and store them. The flow is:

1. `AutoPurchaseOrderService::handleShortfall()` calls `createPurchaseOrderForDigitalProduct()` — the PO is created and the Ezcards API accepts the order.
2. `allocateDigitalProducts()` finds no new vouchers yet → order stays `PROCESSING`.
3. Later, `EzcardsVoucherCodeService` fetches the codes and persists them, then fires `NewVouchersAvailable`.
4. `FulfillPendingSaleOrders` listener wakes up, finds the `PROCESSING` order, allocates the new vouchers, and marks it `COMPLETED`.

This means **Ezcards IS supported** in the auto-PO path — it just completes asynchronously rather than synchronously.

---

## Error Handling

| Scenario | Where caught | Behaviour |
|----------|-------------|-----------|
| Product has no digital products | `validateItemInventory()` | `DB::rollBack()`, order rejected |
| External API call fails (Gift2Games / Ezcards) | `PurchaseOrderService::processSupplierItems()` | Supplier status → `FAILED`; exception propagates up; `DB::rollBack()` in `createOrder()` — **entire order rejected** |
| Partial internal stock | `allocateDigitalProducts()` (no throw) | Order saved as `PROCESSING`; `FulfillPendingSaleOrders` retries |
| `FulfillPendingSaleOrders` retry fails | Caught per-order | Logged; order stays `PROCESSING`; retried on next `NewVouchersAvailable` |

---

## New `SaleOrderRepository` Method

```php
/**
 * Get all sale orders in PROCESSING status, eager-loading items and their products.
 *
 * @return \Illuminate\Database\Eloquent\Collection<int, SaleOrder>
 */
public function getPendingSaleOrders(): Collection
{
    return SaleOrder::with(['items.product.digitalProducts'])
        ->where('status', Status::PROCESSING->value)
        ->get();
}
```

---

## Testing Plan

### Unit Tests — `AutoPurchaseOrderService`

| Test | Assertion |
|------|-----------|
| No shortfall → `createPurchaseOrderForDigitalProduct` never called | `PurchaseOrderService` mock not invoked |
| Shortfall, Gift2Games supplier → PO dispatched with correct quantity | Mock called once with correct args |
| Shortfall, internal supplier only → returns `false`, no PO | Mock not invoked, returns `false` |
| Shortfall, Ezcards only → PO dispatched (async path) | Mock called; order will stay `PROCESSING` |
| Mixed internal + Gift2Games → PO dispatched for Gift2Games only | Mock called once for external only |

### Feature Tests — `SaleOrderService::createOrder()`

| Test | Assertion |
|------|-----------|
| Sufficient internal stock → order `completed`, no PO, vouchers allocated | `SaleOrder.status === completed`, `PurchaseOrder::count() === 0` |
| Insufficient internal stock (partial) → order `processing`, partial vouchers allocated | `SaleOrder.status === processing` |
| Shortfall, Gift2Games → auto-PO created, vouchers allocated, order `completed` | `PurchaseOrder::count() === 1`, `SaleOrder.status === completed` |
| Shortfall, Ezcards → auto-PO created, order `processing` | `PurchaseOrder::count() === 1`, `SaleOrder.status === processing` |
| External API failure → order rejected, full rollback | `SaleOrder::count() === 0`, `PurchaseOrder::count() === 0` |
| No digital products on product → order rejected | Exception with product name |

### Feature Tests — `FulfillPendingSaleOrders` Listener

| Test | Assertion |
|------|-----------|
| `NewVouchersAvailable` fired → `PROCESSING` orders re-attempted | Listener handles event |
| Sufficient new stock → order transitions `processing → completed`, `SaleOrderCompleted` fired | `SaleOrder.status === completed` |
| Still insufficient → order stays `processing` | `SaleOrder.status === processing` |

---

## Implementation Checklist

- [ ] Add `PROCESSING = 'processing'` to `app/Enums/SaleOrder/Status.php`
- [ ] Create `app/Events/NewVouchersAvailable.php`
- [ ] Create `app/Listeners/FulfillPendingSaleOrders.php` (implements `ShouldQueue`)
- [ ] Register `NewVouchersAvailable → FulfillPendingSaleOrders` in `AppServiceProvider`
- [ ] Add `getPendingSaleOrders()` to `SaleOrderRepository`
- [ ] Create `app/Services/AutoPurchaseOrderService.php` (injects `PurchaseOrderService`)
- [ ] Add `createPurchaseOrderForDigitalProduct()` to `app/Services/PurchaseOrderService.php`
- [ ] Modify `SaleOrderService::validateItemInventory()` — only throw on no-digital-products
- [ ] Modify `SaleOrderService::allocateDigitalProducts()` — return `int` (allocated count), remove final throw
- [ ] Add `SaleOrderService::triggerAutoPurchaseOrdersIfNeeded()` private method
- [ ] Update `SaleOrderService::createOrder()` — partial allocation + `PROCESSING` status logic
- [ ] Inject `AutoPurchaseOrderService` into `SaleOrderService` constructor
- [ ] Fire `event(new NewVouchersAvailable($ids))` in `Gift2GamesVoucherService::storeVouchers()`
- [ ] Fire `event(new NewVouchersAvailable($ids))` in `EzcardsVoucherCodeService` after persisting codes
- [ ] Fire `event(new NewVouchersAvailable($ids))` in voucher CSV import service after commit
- [ ] Write unit tests for `AutoPurchaseOrderService`
- [ ] Write feature tests for `SaleOrderService::createOrder()` (all scenarios above)
- [ ] Write feature tests for `FulfillPendingSaleOrders` listener
- [ ] Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/phpunit` before merging


---

## Background & Context

### Current Flow (Pre-Feature)

```
createOrder()
  └─ validateProductsAndDigitalStock()   ← throws if stock insufficient
  └─ create SaleOrderItems
  └─ allocateDigitalProducts()           ← allocates existing vouchers
  └─ SaleOrderCompleted event dispatched
```

If available vouchers < requested quantity, the **entire order is rejected** — even when an external supplier can fulfil the gap immediately.

### New Flow (Post-Feature)

```
createOrder()
  └─ validateProductsAndDigitalStock()   ← soft check: only rejects if NO external supplier can cover gap
  └─ triggerAutoPurchaseOrdersIfNeeded() ← NEW: creates POs for shortfall from external suppliers
  └─ create SaleOrderItems
  └─ allocateDigitalProducts()           ← allocates vouchers (now includes newly purchased ones)
  └─ SaleOrderCompleted event dispatched
```

---

## Affected Files

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Services/SaleOrderService.php` | **Modify** | Inject `AutoPurchaseOrderService`; add shortfall detection and auto-PO trigger |
| `app/Services/AutoPurchaseOrderService.php` | **Create** | New service — encapsulates all auto-PO logic |
| `app/Services/PurchaseOrderService.php` | **Modify** | Add `createPurchaseOrderForDigitalProduct()` method for single-digital-product POs |

> **Note:** `PurchaseOrderRepository` is now a thin DB-access class (no constructor dependencies, no orchestration). All PO creation logic lives in `PurchaseOrderService`. See `docs/purchase-order-repository-refactor.md` for the completed refactor.

---

## Detailed Design

### 1. `AutoPurchaseOrderService` (New)

**Location:** `app/Services/AutoPurchaseOrderService.php`

**Responsibility:** Given a `Product` and a requested quantity, determine the shortfall per digital product per external supplier and create the corresponding purchase orders.

**Constructor dependencies:**
```php
public function __construct(
    private VoucherAllocationService $voucherAllocationService,
    private PurchaseOrderService $purchaseOrderService,
) {}
```

> **Why `PurchaseOrderService` and not `PurchaseOrderRepository`?** The repository is now a thin DB-access class with zero orchestration logic. All PO creation — transaction management, external API dispatch, voucher storage, status updates — lives in `PurchaseOrderService`. `AutoPurchaseOrderService` must therefore call `PurchaseOrderService::createPurchaseOrderForDigitalProduct()`, not a repository method.

**Key method:**
```php
/**
 * Detect shortfall for a product and trigger purchase orders for external suppliers.
 * Returns true if the shortfall was fully covered, false if it cannot be covered.
 */
public function handleShortfall(Product $product, int $requestedQuantity): bool
```

**Logic:**
1. Load the product's digital products (respecting `fulfillment_mode` ordering — `PRICE` → cost_price ASC, `MANUAL` → pivot priority ASC).
2. For each digital product, get `$available = VoucherAllocationService::getAvailableQuantity()`.
3. Calculate `$shortfall = max(0, $requestedQuantity - $totalAvailable)`. If `$shortfall === 0`, return early.
4. For each digital product with a shortfall:
   - Load its `supplier` relationship.
   - If `supplier->type !== SupplierType::EXTERNAL`, skip — cannot auto-purchase from internal suppliers.
   - Call `PurchaseOrderService::createPurchaseOrderForDigitalProduct($digitalProduct, $shortfall)`.
5. After all POs are created, re-check total available quantity to confirm coverage.
6. Return `true` if now fully covered, `false` otherwise.

---

### 2. `PurchaseOrderService` — New Method

**Method signature:**
```php
public function createPurchaseOrderForDigitalProduct(
    DigitalProduct $digitalProduct,
    int $quantity
): PurchaseOrder
```

**Logic:**
- Convenience wrapper around the existing `createPurchaseOrder()` pipeline.
- Constructs the `items` array from the given digital product and quantity:
  ```php
  [
      'items' => [
          [
              'supplier_id'        => $digitalProduct->supplier_id,
              'digital_product_id' => $digitalProduct->id,
              'quantity'           => $quantity,
          ]
      ]
  ]
  ```
- Delegates entirely to `$this->createPurchaseOrder(...)`, which handles:
  - `GroupBySupplierIdService::groupBySupplierId()`
  - `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`
  - Order number generation
  - `PurchaseOrderPlacementService::placeOrder()` for external suppliers (Ezcards / Gift2Games)
  - `Gift2GamesVoucherService::storeVouchers()` for Gift2Games (synchronous vouchers)
  - `PurchaseOrderRepository` thin writes (`createPurchaseOrder`, `createPurchaseOrderSupplier`, `createPurchaseOrderItem`)
  - `PurchaseOrderStatusService::updateStatus()`
- Returns the created `PurchaseOrder`.

> **Important:** This method is called **inside the same `DB::beginTransaction()`** as the sale order (owned by `SaleOrderService::createOrder()`), so if anything fails the purchase order is also rolled back atomically.

---

### 3. `SaleOrderService` — Modifications

#### 3a. Constructor — inject `AutoPurchaseOrderService`

```php
public function __construct(
    private ProductRepository $productRepository,
    private SaleOrderRepository $saleOrderRepository,
    private VoucherAllocationService $voucherAllocationService,
    private AutoPurchaseOrderService $autoPurchaseOrderService,  // NEW
) {}
```

#### 3b. `validateProductsAndDigitalStock()` — Change Validation Logic

The validation should now distinguish two failure scenarios:

| Scenario | Behaviour |
|----------|-----------|
| Insufficient stock **and** all digital products have **internal** suppliers | ❌ Throw immediately — cannot auto-purchase |
| Insufficient stock **and** at least one digital product has an **external** supplier | ✅ Allow through — shortfall will be covered by auto-PO |
| Product has **no digital products** at all | ❌ Throw immediately |

```php
private function validateItemInventory(Product $product, int $quantity): void
{
    if ($product->digitalProducts->isEmpty()) {
        throw new \Exception("Product {$product->name} has no digital products assigned.");
    }

    $totalAvailable = 0;
    $hasExternalSupplier = false;

    foreach ($product->digitalProducts as $digitalProduct) {
        $totalAvailable += $this->voucherAllocationService->getAvailableQuantity($digitalProduct->id);
        if ($digitalProduct->supplier->type === SupplierType::EXTERNAL->value) {
            $hasExternalSupplier = true;
        }
    }

    if ($totalAvailable < $quantity && !$hasExternalSupplier) {
        throw new \Exception(
            "Insufficient inventory for product {$product->name}. "
            . "Requested: {$quantity}, Available: {$totalAvailable}"
        );
    }
}
```

> **Note:** The `supplier` relationship must be eager-loaded on `$product->digitalProducts` before calling this method. Load it in `validateProductsAndDigitalStock()` using `$product->load('digitalProducts.supplier')`.

#### 3c. `createOrder()` — Add Auto-PO Step

Add the auto-PO trigger **after validation but before allocating digital products**:

```php
public function createOrder(array $data): SaleOrder
{
    DB::beginTransaction();

    try {
        $this->validateProductsAndDigitalStock($data['items']);

        // NEW: Create purchase orders for any external-supplier shortfalls
        $this->triggerAutoPurchaseOrders($data['items']);

        $saleOrder = $this->saleOrderRepository->createSaleOrder([...]);

        // ... rest of existing logic unchanged
    }
}
```

#### 3d. New private method `triggerAutoPurchaseOrders()`

```php
private function triggerAutoPurchaseOrders(array $items): void
{
    foreach ($items as $itemData) {
        $product = $this->productRepository->getProductById($itemData['product_id']);
        $quantity = $itemData['quantity'];

        $totalAvailable = 0;
        foreach ($product->digitalProducts as $digitalProduct) {
            $totalAvailable += $this->voucherAllocationService->getAvailableQuantity($digitalProduct->id);
        }

        if ($totalAvailable < $quantity) {
            $covered = $this->autoPurchaseOrderService->handleShortfall($product, $quantity);

            if (!$covered) {
                throw new \Exception(
                    "Could not cover inventory shortfall for product {$product->name} "
                    . "via external suppliers. Requested: {$quantity}, Available: {$totalAvailable}"
                );
            }
        }
    }
}
```

---

### 7. Event Registration

Register the new listener pair in `app/Providers/AppServiceProvider.php` boot method (Laravel 12 auto-discovery via `#[AsEventListener]` attribute is also acceptable):

```php
Event::listen(
    NewVouchersAvailable::class,
    FulfillPendingSaleOrders::class,
);
```

### 8. Fire `NewVouchersAvailable` at Every Voucher Ingestion Point

| Location | Where to fire |
|----------|--------------|
| `Gift2GamesVoucherService::storeVouchers()` | After bulk-inserting Gift2Games vouchers |
| Voucher CSV import service | After the import transaction commits |
| `EzcardsVoucherCodeService` | After fetching and persisting Ezcards codes |

```php
event(new NewVouchersAvailable($digitalProductIds));
```

---

## Data Flow Diagram

```
createOrder(items)
    │
    ├─ validateProductsAndDigitalStock()
    │       └─ for each item:
    │               └─ if no digital products → THROW (unrecoverable)
    │               └─ all shortfalls → PASS (recoverable)
    │
    ├─ triggerAutoPurchaseOrdersIfNeeded()
    │       └─ for each item where available < requested:
    │               └─ AutoPurchaseOrderService::handleShortfall(product, shortfall)
    │                       └─ for each digital product with eligible external supplier:
    │                               └─ PurchaseOrderService::createPurchaseOrderForDigitalProduct()
    │                                       ├─ PurchaseOrderPlacementService::placeOrder()
    │                                       │       ├─ Gift2GamesPlaceOrderService → vouchers returned synchronously
    │                                       │       └─ EzcardsPlaceOrderService   → vouchers arrive asynchronously
    │                                       ├─ (Gift2Games) Gift2GamesVoucherService::storeVouchers()
    │                                       │       └─ event(NewVouchersAvailable)  ← triggers background fulfil
    │                                       └─ PurchaseOrderRepository thin writes
    │
    ├─ createSaleOrder() [status = PROCESSING]
    ├─ for each item:
    │       ├─ create SaleOrderItem
    │       └─ allocateDigitalProducts() ← partial allocation; returns count allocated
    │
    ├─ if fully allocated  → status = COMPLETED → event(SaleOrderCompleted)
    └─ if partially allocated → status = PROCESSING (listener will complete later)
                                    │
              ┌─────────────────────┘
              │  (later, when new vouchers arrive)
              ▼
    NewVouchersAvailable event fired
              │
              ▼
    FulfillPendingSaleOrders listener (queued)
              └─ for each PROCESSING sale order:
                      └─ re-attempt allocateDigitalProducts() for remaining qty
                              ├─ if now fully allocated → COMPLETED → SaleOrderCompleted
                              └─ if still short → stays PROCESSING (retry on next event)
```

---

## Supplier-Specific Behaviour

| Supplier | Voucher Timing | Allocation Path |
|----------|---------------|-----------------|
| **Internal** | Pre-stocked — vouchers already in DB | Immediate if stock ≥ qty; partial + `PROCESSING` if stock < qty |
| **Gift2Games** | Synchronous — returned in API response, stored before `allocateDigitalProducts()` runs | Immediate — order completes in same request |
| **Ezcards** | Asynchronous — fetched later by `EzcardsVoucherCodeService` poller | Auto-PO dispatched; order stays `PROCESSING`; `FulfillPendingSaleOrders` completes it when codes arrive |

---

## Ezcards Edge Case

Ezcards vouchers are **not** returned in the purchase order API response. `EzcardsVoucherCodeService` polls later to fetch and store them. The flow is:

1. `AutoPurchaseOrderService::handleShortfall()` calls `createPurchaseOrderForDigitalProduct()` — the PO is created and the Ezcards API accepts the order.
2. `allocateDigitalProducts()` finds no new vouchers yet → order stays `PROCESSING`.
3. Later, `EzcardsVoucherCodeService` fetches the codes and persists them, then fires `NewVouchersAvailable`.
4. `FulfillPendingSaleOrders` listener wakes up, finds the `PROCESSING` order, allocates the new vouchers, and marks it `COMPLETED`.

This means **Ezcards IS supported** in the auto-PO path — it just completes asynchronously rather than synchronously.

---

## Error Handling

| Scenario | Where caught | Behaviour |
|----------|-------------|-----------|
| Product has no digital products | `validateItemInventory()` | `DB::rollBack()`, order rejected |
| External API call fails (Gift2Games / Ezcards) | `PurchaseOrderService::processSupplierItems()` | Supplier status → `FAILED`; exception propagates up; `DB::rollBack()` in `createOrder()` — **entire order rejected** |
| Partial internal stock | `allocateDigitalProducts()` (no throw) | Order saved as `PROCESSING`; `FulfillPendingSaleOrders` retries |
| `FulfillPendingSaleOrders` retry fails | Caught per-order | Logged; order stays `PROCESSING`; retried on next `NewVouchersAvailable` |

---

## New `SaleOrderRepository` Method

```php
/**
 * Get all sale orders in PROCESSING status, eager-loading items and their products.
 *
 * @return \Illuminate\Database\Eloquent\Collection<int, SaleOrder>
 */
public function getPendingSaleOrders(): Collection
{
    return SaleOrder::with(['items.product.digitalProducts'])
        ->where('status', Status::PROCESSING->value)
        ->get();
}
```

---

## Testing Plan

### Unit Tests — `AutoPurchaseOrderService`

| Test | Assertion |
|------|-----------|
| No shortfall → `createPurchaseOrderForDigitalProduct` never called | `PurchaseOrderService` mock not invoked |
| Shortfall, Gift2Games supplier → PO dispatched with correct quantity | Mock called once with correct args |
| Shortfall, internal supplier only → returns `false`, no PO | Mock not invoked, returns `false` |
| Shortfall, Ezcards only → PO dispatched (async path) | Mock called; order will stay `PROCESSING` |
| Mixed internal + Gift2Games → PO dispatched for Gift2Games only | Mock called once for external only |

### Feature Tests — `SaleOrderService::createOrder()`

| Test | Assertion |
|------|-----------|
| Sufficient internal stock → order `completed`, no PO, vouchers allocated | `SaleOrder.status === completed`, `PurchaseOrder::count() === 0` |
| Insufficient internal stock (partial) → order `processing`, partial vouchers allocated | `SaleOrder.status === processing` |
| Shortfall, Gift2Games → auto-PO created, vouchers allocated, order `completed` | `PurchaseOrder::count() === 1`, `SaleOrder.status === completed` |
| Shortfall, Ezcards → auto-PO created, order `processing` | `PurchaseOrder::count() === 1`, `SaleOrder.status === processing` |
| External API failure → order rejected, full rollback | `SaleOrder::count() === 0`, `PurchaseOrder::count() === 0` |
| No digital products on product → order rejected | Exception with product name |

### Feature Tests — `FulfillPendingSaleOrders` Listener

| Test | Assertion |
|------|-----------|
| `NewVouchersAvailable` fired → `PROCESSING` orders re-attempted | Listener handles event |
| Sufficient new stock → order transitions `processing → completed`, `SaleOrderCompleted` fired | `SaleOrder.status === completed` |
| Still insufficient → order stays `processing` | `SaleOrder.status === processing` |

---

## Implementation Checklist

- [ ] Add `PROCESSING = 'processing'` to `app/Enums/SaleOrder/Status.php`
- [ ] Create `app/Events/NewVouchersAvailable.php`
- [ ] Create `app/Listeners/FulfillPendingSaleOrders.php` (implements `ShouldQueue`)
- [ ] Register `NewVouchersAvailable → FulfillPendingSaleOrders` in `AppServiceProvider`
- [ ] Add `getPendingSaleOrders()` to `SaleOrderRepository`
- [ ] Create `app/Services/AutoPurchaseOrderService.php` (injects `PurchaseOrderService`)
- [ ] Add `createPurchaseOrderForDigitalProduct()` to `app/Services/PurchaseOrderService.php`
- [ ] Modify `SaleOrderService::validateItemInventory()` — only throw on no-digital-products
- [ ] Modify `SaleOrderService::allocateDigitalProducts()` — return `int` (allocated count), remove final throw
- [ ] Add `SaleOrderService::triggerAutoPurchaseOrdersIfNeeded()` private method
- [ ] Update `SaleOrderService::createOrder()` — partial allocation + `PROCESSING` status logic
- [ ] Inject `AutoPurchaseOrderService` into `SaleOrderService` constructor
- [ ] Fire `event(new NewVouchersAvailable($ids))` in `Gift2GamesVoucherService::storeVouchers()`
- [ ] Fire `event(new NewVouchersAvailable($ids))` in `EzcardsVoucherCodeService` after persisting codes
- [ ] Fire `event(new NewVouchersAvailable($ids))` in voucher CSV import service after commit
- [ ] Write unit tests for `AutoPurchaseOrderService`
- [ ] Write feature tests for `SaleOrderService::createOrder()` (all scenarios above)
- [ ] Write feature tests for `FulfillPendingSaleOrders` listener
- [ ] Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/phpunit` before merging
