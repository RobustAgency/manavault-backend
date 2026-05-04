# Implementation Plan: Store Failed Purchase Orders & Sale Order Status Endpoint

## Overview

This document describes the plan to implement two related enhancements:

1. **Store Failed Purchase Orders** — persist failure reasons and metadata when a purchase order fails
2. **Sale Order Status Endpoint** — an idempotent endpoint ManaStore calls to either place a new sale order or retrieve the current status of an existing one, including purchase order failure reasons

---

## Business Context

- When a sale order arrives from ManaStore, the end user's payment is **immediately deducted** on the ManaStore side.
- The sale order status in Manavault will always remain **`processing`** — even when all purchase orders fail — because the order still needs to be fulfilled manually by an admin.
- This endpoint is used by **ManaStore admins** to inspect why fulfillment is stuck and decide what corrective action to take.
- The endpoint must be **idempotent**: ManaStore may call it multiple times with the same `order_number`. The first call creates the order; subsequent calls return the current status without creating duplicates.

---

## Current State

### Existing `POST /sale-orders` endpoint

Creates a new sale order. Currently enforces `unique:sale_orders,order_number` in `StoreSaleOrderRequest`, so a duplicate `order_number` results in a validation error. This behavior needs to change for the new endpoint.

### How Failures Are Handled Today

When `PlaceExternalPurchaseOrderJob` catches an exception:

```
Exception caught
    → PurchaseOrderSupplier.status = FAILED
    → PurchaseOrderStatusService->updateStatus() (cascades to PurchaseOrder)
    → Log::error(...) with context
```

**The problem:** The failure reason is only written to the application log. It is never persisted to the database, so there is no way to return it via an API response.

---

## Part 1 — Store Failed Purchase Orders

### 1.1 Database Migration

Add a `failure_reason` column to the `purchase_order_suppliers` table (not `purchase_orders`), because failure happens at the **supplier** level — each supplier call can fail independently with its own message.

**Migration:** `add_failure_reason_to_purchase_order_suppliers`

```php
Schema::table('purchase_order_suppliers', function (Blueprint $table) {
    $table->text('failure_reason')->nullable()->after('status');
});
```

> **Why `purchase_order_suppliers` and not `purchase_orders`?**
> A single purchase order can involve multiple suppliers. Each can fail with a different reason. Supplier-level granularity avoids concatenation and preserves the full picture.

### 1.2 Model Update

In `PurchaseOrderSupplier`, add `failure_reason` to `$fillable`:

```php
protected $fillable = [
    'purchase_order_id',
    'supplier_id',
    'transaction_id',
    'status',
    'failure_reason',   // ← add this
];
```

### 1.3 Job Update — `PlaceExternalPurchaseOrderJob`

In the `catch` block of `handle()`, persist the failure reason alongside the status:

**Before (current):**
```php
} catch (\Exception $e) {
    $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED]);
    $this->purchaseOrderStatusService->updateStatus($this->purchaseOrder);
    Log::error('Failed to place external purchase order', [...]);
}
```

**After:**
```php
} catch (\Exception $e) {
    $this->purchaseOrderSupplier->update([
        'status'         => PurchaseOrderSupplierStatus::FAILED,
        'failure_reason' => $e->getMessage(),
    ]);
    $this->purchaseOrderStatusService->updateStatus($this->purchaseOrder);
    Log::error('Failed to place external purchase order', [...]);
}
```

Minimal, targeted change — no new services or abstractions needed.

---

## Part 2 — Sale Order Status Endpoint (Idempotent Place-or-Check)

### Design: Single Idempotent Endpoint

Rather than two separate endpoints (one for create, one for status), the new endpoint handles both in one call:

```
POST /v1/sale-orders/status
```

**Flow:**

```
Receive request with order_number + items
    ↓
Does a sale order with this order_number already exist?
    ├── NO  → Create it (same logic as existing store())
    │           → Return status response (status: processing, empty purchase_orders list)
    └── YES → Skip creation
              → Load its purchase orders + supplier details
              → Return status response with PO breakdown and failure reasons
```

This is idempotent by design — calling it 10 times with the same `order_number` always returns the current state without side effects after the first call.

### Why a new route instead of modifying the existing `POST /sale-orders`?

The existing `store` endpoint is used separately and has `unique:sale_orders,order_number` validation baked into `StoreSaleOrderRequest`. Modifying it risks breaking existing ManaStore integrations. A dedicated route keeps concerns separated and is non-breaking.

### 2.1 Route

Add to `/routes/manastore/v1/api.php` inside the `manastore.auth` middleware group:

```php
Route::post('/sale-orders/status', [SaleOrderController::class, 'placeOrStatus']);
```

> **Route ordering:** Laravel matches routes top-to-bottom. Since `sale-orders/status` is a literal segment (not a parameter), place it **before** any `sale-orders/{saleOrder}` routes to prevent it being captured as a model binding.

### 2.2 Form Request — `PlaceOrStatusSaleOrderRequest`

Create `app/Http/Requests/SaleOrder/PlaceOrStatusSaleOrderRequest.php`.

Key difference from `StoreSaleOrderRequest`: remove the `unique` rule on `order_number` — the controller handles the find-or-create logic.

```php
public function rules(): array
{
    return [
        'order_number'             => ['required', 'string', 'max:255'],
        'source'                   => ['nullable', 'string', 'max:255'],
        'items'                    => ['required', 'array', 'min:1'],
        'items.*.product_id'       => ['required', 'integer', 'exists:products,id'],
        'items.*.quantity'         => ['required', 'integer', 'min:1'],
    ];
}
```

### 2.3 Controller Method — `placeOrStatus()`

In `app/Http/Controllers/Api/ManaStore/V1/SaleOrderController.php`:

```php
public function placeOrStatus(PlaceOrStatusSaleOrderRequest $request): JsonResponse
{
    try {
        $validated = $request->validated();

        $saleOrder = SaleOrder::where('order_number', $validated['order_number'])->first();

        if (!$saleOrder) {
            // First call — create the order
            $saleOrder = $this->saleOrderService->createOrder($validated);
        }

        // Always reload with PO details for the status response
        $saleOrder->load([
            'purchaseOrders.purchaseOrderSuppliers.supplier',
        ]);

        return response()->json([
            'error'   => false,
            'message' => 'Sale order status retrieved successfully.',
            'data'    => new SaleOrderStatusResource($saleOrder),
        ], 200);

    } catch (\Exception $e) {
        logger()->error('Failed to place or retrieve sale order status', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'error'   => true,
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### 2.4 API Resource — `SaleOrderStatusResource`

Create `app/Http/Resources/ManaStore/V1/SaleOrderStatusResource.php`.

**Response shape (newly created order — no POs yet):**
```json
{
  "error": false,
  "message": "Sale order status retrieved successfully.",
  "data": {
    "sale_order_number": "SO-20260504-ABC123",
    "sale_order_status": "processing",
    "purchase_orders": []
  }
}
```

**Response shape (existing order with failed POs):**
```json
{
  "error": false,
  "message": "Sale order status retrieved successfully.",
  "data": {
    "sale_order_number": "SO-20260504-ABC123",
    "sale_order_status": "processing",
    "purchase_orders": [
      {
        "order_number": "PO-20260504-XYZ789",
        "status": "failed",
        "suppliers": [
          {
            "supplier": "Gift2Games",
            "status": "failed",
            "failure_reason": "Insufficient balance on supplier account"
          }
        ]
      },
      {
        "order_number": "PO-20260504-DEF456",
        "status": "processing",
        "suppliers": [
          {
            "supplier": "EzCards",
            "status": "processing",
            "failure_reason": null
          }
        ]
      }
    ]
  }
}
```

**Resource implementation:**

```php
public function toArray(Request $request): array
{
    $purchaseOrders = $this->purchaseOrders->map(function ($po) {
        $suppliers = $po->purchaseOrderSuppliers->map(fn($pos) => [
            'supplier'       => $pos->supplier->name,
            'status'         => $pos->status,
            'failure_reason' => $pos->failure_reason,
        ]);

        return [
            'order_number' => $po->order_number,
            'status'       => $po->status,
            'suppliers'    => $suppliers,
        ];
    });

    return [
        'sale_order_number' => $this->order_number,
        'sale_order_status' => $this->status,
        'purchase_orders'   => $purchaseOrders,
    ];
}
```

---

## Execution Order

| Step | What | File(s) Touched |
|---|---|---|
| 1 | Create migration for `failure_reason` | `database/migrations/` |
| 2 | Update `PurchaseOrderSupplier` fillable | `app/Models/PurchaseOrderSupplier.php` |
| 3 | Persist failure reason in job catch block | `app/Jobs/PlaceExternalPurchaseOrderJob.php` |
| 4 | Create `PlaceOrStatusSaleOrderRequest` | `app/Http/Requests/SaleOrder/PlaceOrStatusSaleOrderRequest.php` |
| 5 | Add `placeOrStatus` route | `routes/manastore/v1/api.php` |
| 6 | Add `placeOrStatus()` controller method | `app/Http/Controllers/Api/ManaStore/V1/SaleOrderController.php` |
| 7 | Create `SaleOrderStatusResource` | `app/Http/Resources/ManaStore/V1/SaleOrderStatusResource.php` |

---

## Open Question

**Failure reason sanitization:** Raw `$e->getMessage()` from supplier SDKs may include internal details (URLs, credentials, stack traces). Should the job store the raw message, or should each supplier service normalize it to a clean, admin-readable string? Recommend storing raw for now (it's admin-facing only) and sanitizing later if needed.
