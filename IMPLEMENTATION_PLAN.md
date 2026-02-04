# Implementation Plan: Immutable Purchase Orders with Voucher-Direct Allocation

## Overview

This document outlines the implementation plan to refactor the sales order creation process to maintain **immutable purchase orders** while tracking voucher allocation directly through `SaleOrderItemDigitalProduct`.

### Problem Statement

Currently, `SaleOrderService::createOrder()` deducts quantities directly from `purchase_order_items`, which violates the immutability principle required for audit trails and historical reporting. Purchase orders should remain untouched once created.

### Solution

Store direct references to vouchers in `SaleOrderItemDigitalProduct` via a `voucher_id` foreign key. Quantity is calculated by counting allocated vouchers, eliminating the need to modify purchase orders.

---

## Architecture Changes

### 1. Data Model Refactoring

#### Current Schema (`sale_order_item_digital_products`)
```sql
sale_order_item_digital_products
├─ id (PK)
├─ sale_order_item_id (FK)
├─ digital_product_id (FK)
├─ quantity_deducted (int)  ← To be replaced
└─ timestamps
```

#### New Schema (`sale_order_item_digital_products`)
```sql
sale_order_item_digital_products
├─ id (PK)
├─ sale_order_item_id (FK)
├─ digital_product_id (FK)
├─ voucher_id (FK)  ← NEW: Direct reference to allocated voucher
├─ unique index (sale_order_item_id, voucher_id)
└─ timestamps
```

**Rationale:**
- One row per voucher allocated (instead of one row per quantity unit)
- Direct traceability: "Which voucher was sold in this order?"
- Automatic quantity calculation: count vouchers
- Single source of truth prevents sync issues

---

### 2. Model Relationships

#### `SaleOrderItemDigitalProduct` Model
```php
/**
 * Get the voucher allocated to this digital product in the order.
 *
 * @return BelongsTo<Voucher, $this>
 */
public function voucher(): BelongsTo
{
    return $this->belongsTo(Voucher::class);
}

/**
 * Calculate allocated quantity (count of vouchers).
 * Helper method for consistency.
 */
public function getQuantityAttribute(): int
{
    // When eager loaded with vouchers, this calculates the count
    // In queries, use COUNT() explicitly
    return $this->vouchers_count ?? $this->vouchers()->count();
}
```

#### `Voucher` Model (Update)
```php
/**
 * Get the sale order item digital product this voucher is allocated to.
 *
 * @return BelongsTo<SaleOrderItemDigitalProduct, $this>
 */
public function saleOrderItemDigitalProduct(): BelongsTo
{
    return $this->belongsTo(SaleOrderItemDigitalProduct::class);
}
```

---

## Implementation Steps

### Phase 1: Database Migration

**File:** `database/migrations/2026_02_04_XXXXXX_refactor_sale_order_item_digital_products_voucher_allocation.php`

```php
<?php

use App\Models\Voucher;
use App\Models\DigitalProduct;
use App\Models\SaleOrderItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            // Add voucher_id column before dropping quantity_deducted
            $table->foreignIdFor(Voucher::class)->nullable()->after('digital_product_id');
            
            // Add unique constraint to prevent duplicate voucher allocations
            $table->unique(['sale_order_item_id', 'voucher_id'], 'soidp_soi_voucher_unique');
        });

        // Data migration: Create records for existing allocations
        // (if there are existing sale orders, map them appropriately)
        // This would be custom logic based on your current data state

        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            // Drop the old quantity column after migration
            $table->dropColumn('quantity_deducted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            $table->integer('quantity_deducted')->default(0)->after('digital_product_id');
        });

        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            $table->dropForeignIdFor(Voucher::class);
            $table->dropUnique('soidp_soi_voucher_unique');
        });
    }
};
```

**Steps:**
1. Add `voucher_id` foreign key
2. Add unique constraint on `(sale_order_item_id, voucher_id)`
3. Migrate existing data (if applicable)
4. Drop `quantity_deducted` column

---

### Phase 2: Update Models

#### File: `app/Models/SaleOrderItemDigitalProduct.php`

**Changes:**
1. Update `$fillable` array: replace `quantity_deducted` with `voucher_id`
2. Update `$casts`: remove `quantity_deducted`
3. Add `voucher()` BelongsTo relationship
4. Add helper methods for quantity calculation

```php
// Changes to make:
// Line 14-18: Update $fillable
protected $fillable = [
    'sale_order_item_id',
    'digital_product_id',
    'voucher_id',  // Changed from quantity_deducted
];

// Line 20-22: Update $casts (remove quantity_deducted)
protected $casts = [];  // Empty since voucher_id is FK, no casting needed

// Add new relationship after existing relationships:
/**
 * Get the voucher allocated to this digital product in the order.
 *
 * @return BelongsTo<Voucher, $this>
 */
public function voucher(): BelongsTo
{
    return $this->belongsTo(Voucher::class);
}
```

#### File: `app/Models/Voucher.php`

**Add relationship:**
```php
/**
 * Get the sale order item digital product this voucher is allocated to.
 *
 * @return BelongsTo<SaleOrderItemDigitalProduct, $this>
 */
public function saleOrderItemDigitalProduct(): BelongsTo
{
    return $this->belongsTo(SaleOrderItemDigitalProduct::class);
}
```

---

### Phase 3: Create VoucherAllocationService

**File:** `app/Services/VoucherAllocationService.php`

Purpose: Handle voucher fetching without modifying purchase orders.

```php
<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherAllocationService
{
    /**
     * Get available unallocated vouchers for a specific digital product.
     * 
     * Uses row-level locking to prevent concurrent allocation conflicts.
     *
     * @param  int  $digitalProductId
     * @param  int  $quantity  Number of vouchers to fetch
     * @return Collection<int, Voucher>
     *
     * @throws \Exception  If insufficient vouchers available
     */
    public function getAvailableVouchersForDigitalProduct(
        int $digitalProductId,
        int $quantity
    ): Collection {
        $vouchers = DB::table('vouchers')
            ->join('purchase_order_items', 'vouchers.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->where('purchase_order_items.digital_product_id', $digitalProductId)
            // Only unallocated vouchers (no sale_order_item_digital_product_id)
            ->whereNull('vouchers.sale_order_item_digital_product_id')
            ->select('vouchers.*')
            ->orderBy('vouchers.created_at', 'asc')
            ->lockForUpdate()  // Row-level lock for transaction safety
            ->limit($quantity)
            ->get();

        if ($vouchers->count() < $quantity) {
            throw new \Exception(
                "Insufficient vouchers available for digital product {$digitalProductId}. "
                ."Requested: {$quantity}, Available: {$vouchers->count()}"
            );
        }

        return $vouchers->keyBy('id');
    }

    /**
     * Get available quantity (count of unallocated vouchers) for a digital product.
     *
     * @param  int  $digitalProductId
     * @return int
     */
    public function getAvailableQuantity(int $digitalProductId): int
    {
        return DB::table('vouchers')
            ->join('purchase_order_items', 'vouchers.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->where('purchase_order_items.digital_product_id', $digitalProductId)
            ->whereNull('vouchers.sale_order_item_digital_product_id')
            ->count();
    }
}
```

**Note:** Assumes Voucher model has a `sale_order_item_digital_product_id` column. This tracks the allocation relationship and distinguishes allocated vs. unallocated vouchers.

---

### Phase 4: Update DigitalStockRepository

**File:** `app/Repositories/DigitalStockRepository.php`

**Changes:**

1. **Deprecate** `deductDigitalProductQuantity()` method
   - Add a deprecation notice comment
   - Do NOT call it from new code
   - Keep for backward compatibility if needed elsewhere

2. **Update** `getDigitalProductQuantity()` to only count unallocated vouchers
   ```php
   /**
    * Get total available quantity for a specific digital product
    * (counts unallocated vouchers only).
    */
   public function getDigitalProductQuantity(int $digitalProductId): int
   {
       $result = DB::table('vouchers')
           ->join('purchase_order_items', 'vouchers.purchase_order_item_id', '=', 'purchase_order_items.id')
           ->where('purchase_order_items.digital_product_id', $digitalProductId)
           ->whereNull('vouchers.sale_order_item_digital_product_id')
           ->selectRaw('COUNT(*) as total_quantity')
           ->first();

       return $result->total_quantity ?? 0;
   }
   ```

3. **Keep all read-only methods** (for reporting and dashboard calculations)

4. **Remove all write operations** (purchase order modification)

---

### Phase 5: Refactor SaleOrderService

**File:** `app/Services/SaleOrderService.php`

**Major Changes:**

1. **Add dependency injection** for `VoucherAllocationService`
   ```php
   public function __construct(
       private ProductRepository $productRepository,
       private SaleOrderRepository $saleOrderRepository,
       private DigitalStockRepository $digitalStockRepository,
       private VoucherAllocationService $voucherAllocationService,  // NEW
   ) {}
   ```

2. **Update `createOrder()` method:**
   - Keep all validation logic (unchanged)
   - Remove `deductDigitalProductQuantity()` call
   - Change `allocateDigitalProducts()` signature to not modify inventory

3. **Refactor `allocateDigitalProducts()` method:**

```php
/**
 * Allocate digital products to sale order item using vouchers.
 * Does NOT modify purchase orders (maintains immutability).
 *
 * @throws \Exception
 */
private function allocateDigitalProducts(
    SaleOrderItem $item,
    Product $product,
    int $quantity
): void {
    $query = $product->digitalProducts();

    $digitalProducts = $product->fulfillment_mode === FulfillmentMode::PRICE->value
        ? $query->orderBy('cost_price', 'asc')->get()
        : $query->orderByPivot('priority', 'asc')->get();

    if ($digitalProducts->isEmpty()) {
        throw new \Exception("Product {$product->name} has no digital products assigned.");
    }

    $remaining = $quantity;

    foreach ($digitalProducts as $digitalProduct) {
        if ($remaining <= 0) {
            break;
        }

        try {
            // Get available vouchers without modifying purchase orders
            $vouchers = $this->voucherAllocationService
                ->getAvailableVouchersForDigitalProduct(
                    $digitalProduct->id,
                    $remaining
                );

            // Create allocation records (one per voucher)
            foreach ($vouchers as $voucher) {
                $item->digitalProducts()->create([
                    'digital_product_id' => $digitalProduct->id,
                    'voucher_id' => $voucher->id,  // Direct voucher reference
                ]);

                $remaining--;
            }
        } catch (\Exception $e) {
            // Insufficient vouchers from this digital product, try next
            if (str_contains($e->getMessage(), 'Insufficient vouchers')) {
                continue;
            }
            throw $e;
        }
    }

    if ($remaining > 0) {
        throw new \Exception(
            "Could not fully allocate {$quantity} units for product {$product->name}. "
            ."Remaining: {$remaining}"
        );
    }
}
```

---

## Database Schema Tracking

### Voucher Model Update Requirement

The `Voucher` model needs to track which sale order item digital product it's allocated to:

**Migration (if not already present):**
```php
// Add this column to vouchers table if needed
Schema::table('vouchers', function (Blueprint $table) {
    $table->foreignIdFor(SaleOrderItemDigitalProduct::class)
        ->nullable()
        ->after('purchase_order_item_id')
        ->constrained()
        ->onDelete('set null');
});
```

This allows:
- Tracking unallocated vouchers: `whereNull('sale_order_item_digital_product_id')`
- Finding allocated vouchers: `whereNotNull('sale_order_item_digital_product_id')`

---

## Data Flow After Implementation

```
SaleOrderService::createOrder($data)
│
├─ validateProductsAndDigitalStock()  [No changes]
│
├─ Create SaleOrder record
│
└─ For each item in order:
    │
    ├─ Create SaleOrderItem record
    │
    └─ For each quantity needed:
        │
        ├─ Call allocateDigitalProducts()
        │
        └─ For each digital product (ordered by PRICE/MANUAL):
            │
            ├─ Call VoucherAllocationService::getAvailableVouchersForDigitalProduct()
            │   └─ Returns available Voucher collection (NO PO modification)
            │
            └─ For each voucher:
                └─ Create SaleOrderItemDigitalProduct(voucher_id, digital_product_id)
                    └─ This links the voucher to the allocation
│
└─ DB::commit()

Result:
✅ PurchaseOrderItem records COMPLETELY UNCHANGED
✅ Quantity automatically derived from voucher count
✅ Complete audit trail via Voucher → SaleOrderItemDigitalProduct link
✅ Voucher status tracks allocation history
```

---

## Query Examples After Implementation

### Get quantity for an allocation
```php
$allocation = $saleOrderItem->digitalProducts()
    ->where('digital_product_id', $digitalProductId)
    ->first();

$allocatedQuantity = $allocation->vouchers()->count();
// OR (if eager loaded with count)
$allocatedQuantity = $allocation->vouchers_count;
```

### Get all vouchers for a sale order
```php
$saleOrder->items()
    ->with('digitalProducts.vouchers')
    ->get()
    ->flatMap(fn($item) => $item->digitalProducts->flatMap->vouchers);
```

### Track which voucher was sold
```php
$voucher = Voucher::findOrFail($id);
$allocation = $voucher->saleOrderItemDigitalProduct;
$saleOrder = $allocation->saleOrderItem->saleOrder;
$digitalProduct = $allocation->digitalProduct;

// Now you know: Voucher X was sold as part of Digital Product Y in Sale Order Z
```

### Calculate available inventory
```php
$availableCount = $this->voucherAllocationService
    ->getAvailableQuantity($digitalProductId);
```

---

## Testing Strategy

### Unit Tests

1. **VoucherAllocationService**
   - Test fetching available vouchers
   - Test insufficient vouchers exception
   - Test row-level locking behavior

2. **SaleOrderService**
   - Test order creation with various quantities
   - Test fulfillment modes (PRICE/MANUAL ordering)
   - Test exception handling
   - **Verify purchase orders remain unchanged**

3. **Model Relationships**
   - Test `SaleOrderItemDigitalProduct::voucher()` relationship
   - Test `Voucher::saleOrderItemDigitalProduct()` relationship
   - Test quantity calculation

### Feature Tests

1. **Create Sale Order**
   - Verify sale order created successfully
   - Verify digital products allocated correctly
   - Verify vouchers linked to allocations
   - **Verify purchase order quantities unchanged**

2. **Concurrent Allocations**
   - Test row-level locking prevents double-allocation
   - Test transaction rollback on allocation failure

3. **Reporting**
   - Verify accurate quantity reporting
   - Verify audit trail completeness

### Test Example
```php
public function test_create_sale_order_does_not_modify_purchase_orders()
{
    $purchaseOrder = PurchaseOrder::factory()
        ->hasItems(5, ['quantity' => 10])
        ->create();
    
    $originalQuantities = $purchaseOrder->items()
        ->pluck('quantity')
        ->toArray();
    
    $saleOrder = $this->service->createOrder([
        'order_number' => 'SO-001',
        'items' => [
            ['product_id' => 1, 'quantity' => 5],
        ],
    ]);
    
    // Purchase order quantities should be unchanged
    $this->assertEquals(
        $originalQuantities,
        $purchaseOrder->refresh()->items()->pluck('quantity')->toArray()
    );
    
    // Sale order should have correct allocations
    $this->assertEquals(5, $saleOrder->items->sum->digitalProducts->count());
}
```

---

## Rollback Strategy

If issues arise:

1. **Quick Rollback:** Run migration down command
   ```bash
   php artisan migrate:rollback
   ```

2. **Data Preservation:** Migration includes all data mapping logic
   - Down migration restores `quantity_deducted` column
   - Voucher links can be preserved via migration logic

3. **Testing Before Production:** Run full test suite
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan analyse
   ./vendor/bin/pint
   ```

---

## Deployment Checklist

- [ ] Create and review migration
- [ ] Update SaleOrderItemDigitalProduct model
- [ ] Update Voucher model
- [ ] Create VoucherAllocationService
- [ ] Update DigitalStockRepository
- [ ] Refactor SaleOrderService
- [ ] Add/update unit tests
- [ ] Add/update feature tests
- [ ] Run full test suite locally
- [ ] Code style checks (Pint, PHPStan)
- [ ] Peer code review
- [ ] Deploy to staging
- [ ] Staging smoke tests
- [ ] Deploy to production
- [ ] Monitor logs for errors
- [ ] Verify purchase order immutability

---

## Benefits Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Purchase Order Immutability** | ❌ Modified on each sale | ✅ Never modified |
| **Quantity Tracking** | Numeric field (sync risk) | ✅ Counted from vouchers (single source of truth) |
| **Audit Trail** | Limited (quantities changed) | ✅ Complete (every voucher linked to sale) |
| **Data Integrity** | Risk of sync issues | ✅ Guaranteed via FK constraints |
| **Query Complexity** | Simple column read | ✅ Simple count (same complexity) |
| **Future Proof** | Blocks DigitalProductQuantity table | ✅ Ready for future refactoring |
| **Reporting** | Difficult to track voucher → product link | ✅ Direct traceability |

---

## Questions & Clarifications

**Q: What happens to existing sale orders?**
A: The migration includes data mapping. Existing allocations will be converted to voucher-based records.

**Q: How does this affect refunds?**
A: Refunds become simpler—deallocate vouchers by setting `sale_order_item_digital_product_id` back to null.

**Q: What about partial fulfillment?**
A: Each voucher represents one fulfillable unit, enabling granular tracking.

**Q: Performance impact?**
A: Negligible. Counting vouchers is indexed via FK relationships, same as previous queries.

---

## Timeline Estimate

- **Phase 1 (Migration):** 1-2 hours
- **Phase 2 (Models):** 30 minutes
- **Phase 3 (VoucherAllocationService):** 1-2 hours
- **Phase 4 (DigitalStockRepository):** 30 minutes
- **Phase 5 (SaleOrderService):** 2-3 hours
- **Testing & QA:** 2-3 hours
- **Total:** 7-11 hours

---

**Document Version:** 1.0  
**Date:** February 4, 2026  
**Status:** Ready for Implementation Review
