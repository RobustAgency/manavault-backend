# Auto Purchase Order on Sale Order — Implementation Document

## Overview

When creating a sale order, if the available voucher quantity for a product is **less than the requested quantity**, and the product's digital product is sourced from an **external supplier**, the system must automatically create a purchase order for the shortfall quantity before proceeding with the sale order.

This ensures the sale order is never blocked by insufficient stock when external suppliers can fulfil it in real time.

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
| `app/Services/SaleOrderService.php` | **Modify** | Inject `PurchaseOrderRepository`; add shortfall detection and auto-PO trigger |
| `app/Services/AutoPurchaseOrderService.php` | **Create** | New service — encapsulates all auto-PO logic |
| `app/Repositories/PurchaseOrderRepository.php` | **Modify** | Add `createPurchaseOrderForDigitalProduct()` method for single-digital-product POs |

---

## Detailed Design

### 1. `AutoPurchaseOrderService` (New)

**Location:** `app/Services/AutoPurchaseOrderService.php`

**Responsibility:** Given a `Product` and a requested quantity, determine the shortfall per digital product per external supplier and create the corresponding purchase orders.

**Constructor dependencies:**
```php
public function __construct(
    private VoucherAllocationService $voucherAllocationService,
    private PurchaseOrderRepository $purchaseOrderRepository,
) {}
```

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
   - Call `PurchaseOrderRepository::createPurchaseOrderForDigitalProduct($digitalProduct, $shortfall)`.
5. After all POs are created, re-check total available quantity to confirm coverage.
6. Return `true` if now fully covered, `false` otherwise.

---

### 2. `PurchaseOrderRepository` — New Method

**Method signature:**
```php
public function createPurchaseOrderForDigitalProduct(
    DigitalProduct $digitalProduct,
    int $quantity
): PurchaseOrder
```

**Logic:**
- Reuses the existing `createPurchaseOrder()` internal pipeline.
- Constructs the `items` array as:
  ```php
  [
      [
          'supplier_id'        => $digitalProduct->supplier_id,
          'digital_product_id' => $digitalProduct->id,
          'quantity'           => $quantity,
      ]
  ]
  ```
- Calls `groupBySupplierIdService->groupBySupplierId()` → `processPurchaseOrderItems()`.
- For external suppliers (Ezcards / Gift2Games), this triggers the real-time external API order and — for Gift2Games — also stores vouchers immediately.
- For Ezcards, vouchers arrive asynchronously (existing webhook/polling mechanism is unchanged).
- Returns the created `PurchaseOrder`.

> **Important:** This method must be called **inside the same `DB::beginTransaction()`** as the sale order so that if anything fails, the purchase order is also rolled back.

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

## Data Flow Diagram

```
createOrder(items)
    │
    ├─ validateProductsAndDigitalStock()
    │       └─ for each item:
    │               ├─ if no digital products → THROW
    │               ├─ if stock < qty AND no external supplier → THROW
    │               └─ if stock < qty AND has external supplier → PASS (shortfall handled next)
    │
    ├─ triggerAutoPurchaseOrders()           ← NEW
    │       └─ for each item with shortfall:
    │               └─ AutoPurchaseOrderService::handleShortfall(product, qty)
    │                       └─ for each digital product with external supplier:
    │                               └─ PurchaseOrderRepository::createPurchaseOrderForDigitalProduct()
    │                                       ├─ placeExternalOrder() → Ezcards / Gift2Games API
    │                                       ├─ store PurchaseOrder + PurchaseOrderSupplier + PurchaseOrderItems
    │                                       └─ (Gift2Games) store vouchers immediately
    │
    ├─ createSaleOrder()
    ├─ for each item:
    │       ├─ create SaleOrderItem
    │       └─ allocateDigitalProducts()     ← now finds sufficient vouchers
    ├─ update SaleOrder status → COMPLETED
    ├─ DB::commit()
    └─ event(SaleOrderCompleted)
```

---

## Supplier-Specific Behaviour

| Supplier | Voucher Timing | Impact on Sale Order Allocation |
|----------|---------------|--------------------------------|
| **Gift2Games** | Vouchers returned **synchronously** in the API response and stored immediately | `allocateDigitalProducts()` can allocate them in the same request |
| **Ezcards** | Vouchers arrive **asynchronously** (fetched later via polling/webhook) | ⚠️ Sale order allocation will **not** be able to use these vouchers immediately — see [Edge Case: Ezcards](#edge-case-ezcards) below |
| **Internal** | Not eligible for auto-PO | Not applicable |

---

## Edge Case: Ezcards

Because Ezcards vouchers are not returned synchronously, after the purchase order is created the vouchers are not yet in the database. This means `allocateDigitalProducts()` will fail to find sufficient vouchers and will throw:

> `"Could not fully allocate {$quantity} units for product {$product->name}."`

### Proposed Resolution (Decision Required)

**Option A — Block Ezcards from Auto-PO path (simplest)**
- In `AutoPurchaseOrderService::handleShortfall()`, skip Ezcards suppliers entirely.
- Ezcards shortfalls continue to reject the sale order with an insufficient inventory error.
- Pro: No change to existing Ezcards flow. Con: Ezcards products cannot benefit from auto-PO.

**Option B — Deferred Allocation (complex)**
- Create the purchase order and mark the sale order as `PENDING` instead of `COMPLETED`.
- When Ezcards webhooks deliver vouchers (`PurchaseOrderFulfill` event → `SyncProductOnPurchaseOrderFulfillment` listener), re-attempt voucher allocation for pending sale orders.
- Pro: Full auto-PO support for Ezcards. Con: Significant additional complexity; requires a new `PendingSaleOrderAllocation` job/listener.

> **Recommended for initial implementation: Option A.** Option B can be implemented as a follow-up.

---

## Error Handling

| Error | Where Thrown | Behaviour |
|-------|-------------|-----------|
| No digital products on product | `validateItemInventory()` | Order rejected |
| Insufficient stock, no external supplier | `validateItemInventory()` | Order rejected |
| External API call fails (e.g. insufficient balance) | `PurchaseOrderRepository::processPurchaseOrderItems()` | PO supplier status → `FAILED`; `triggerAutoPurchaseOrders()` catches and throws upward; `DB::rollBack()` in `createOrder()` reverts everything |
| PO created but vouchers still insufficient after auto-PO | `triggerAutoPurchaseOrders()` | Order rejected with descriptive message |

---

## Testing Plan

### Unit Tests

| Test | Class Under Test | Assertion |
|------|-----------------|-----------|
| Shortfall is zero → no PO created | `AutoPurchaseOrderService` | `createPurchaseOrderForDigitalProduct` not called |
| Shortfall > 0, external supplier → PO created | `AutoPurchaseOrderService` | PO created with correct quantity |
| Shortfall > 0, internal supplier only → returns false | `AutoPurchaseOrderService` | Returns `false` |
| Mixed internal + external supplier → PO created for external only | `AutoPurchaseOrderService` | PO quantity = external shortfall only |

### Feature Tests

| Test | Assertion |
|------|-----------|
| Sale order with sufficient stock → completes normally, no PO created | `PurchaseOrder::count() === 0` |
| Sale order with shortfall, Gift2Games supplier → auto-PO created, vouchers allocated, order completed | `PurchaseOrder::count() === 1`, `SaleOrder.status === completed` |
| Sale order with shortfall, internal supplier only → order rejected | HTTP 422, error message includes product name |
| Sale order with shortfall, external API failure → order rejected, DB rolled back | `SaleOrder::count() === 0`, `PurchaseOrder::count() === 0` |
| Sale order with shortfall, Ezcards supplier (Option A) → order rejected | HTTP 422 |

---

## Implementation Checklist

- [ ] Create `app/Services/AutoPurchaseOrderService.php`
- [ ] Add `createPurchaseOrderForDigitalProduct()` to `PurchaseOrderRepository`
- [ ] Modify `SaleOrderService::validateItemInventory()` to allow external-supplier shortfalls
- [ ] Add `SaleOrderService::triggerAutoPurchaseOrders()` private method
- [ ] Inject `AutoPurchaseOrderService` into `SaleOrderService` constructor
- [ ] Eager-load `digitalProducts.supplier` in `validateProductsAndDigitalStock()`
- [ ] Decide and document Ezcards handling (Option A vs B)
- [ ] Write unit tests for `AutoPurchaseOrderService`
- [ ] Write feature tests for the updated `SaleOrderService::createOrder()`
- [ ] Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/phpunit` before merging
