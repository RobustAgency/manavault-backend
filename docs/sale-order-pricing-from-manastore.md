# Sale Order Pricing From Manastore — Implementation Document

## Background & Context

### Problem

Currently, the `SaleOrderService::createOrder()` method calculates pricing **server-side in Manavault** using the product's `selling_price` attribute (derived from the associated `DigitalProduct`):

```php
$unitPrice = $product->selling_price;
$subtotal  = $quantity * $unitPrice;
```

However, **Manastore admins can apply discounts directly on products** in the storefront. These discounts are **not reflected in Manavault**, leading to price mismatches between what the customer pays and what the backend records.

### Decision

**All sale order pricing must come from Manastore** as the source of truth.

Manastore will send per-item pricing fields in the `POST /v1/sale-orders` request payload. Manavault will store exactly what Manastore sends, without recalculating.

---

## Manastore Request Payload (New Structure)

The Manastore API will now send enriched item data including pricing, currency, and discount information:

```json
{
  "order_number": "MS-2026-000001",
  "currency": "USD",
  "subtotal": 8368,
  "conversion_fees": 27,
  "total": 8395,
  "items": [
    {
      "product_id": 1,
      "product_variant_id": 202,
      "variant_name": "PlayStation Network Card G2g",
      "product_name": "PlayStation Network Card G2g",
      "quantity": 2,
      "price": 2668,
      "purchase_price": 4508,
      "conversion_fee": 27,
      "total_price": 2668,
      "discount_amount": 2668,
      "currency": "USD"
    }
  ]
}
```

### Pricing Fields (All in Minor Units / Cents)

| Field | Description |
|---|---|
| `price` | Unit price per item (after any Manastore discount) |
| `purchase_price` | Original/cost price per item (before discount) |
| `conversion_fee` | Currency conversion fee for this item |
| `total_price` | Total price for the item (price × quantity - discount, or as Manastore computes it) |
| `discount_amount` | Discount applied to this item |
| `currency` | Currency for this item (e.g., `USD`, `EUR`) |
| `product_variant_id` | Manastore's variant identifier |
| `variant_name` | Display name of the variant |
| `product_name` | Display name of the product |

### Order-Level Fields

| Field | Description |
|---|---|
| `currency` | Order-level currency |
| `subtotal` | Sum of all item totals before conversion fees |
| `conversion_fees` | Total conversion fees for the order |
| `total` | Final order total (subtotal + conversion_fees) |

> **Important:** All monetary values are in **minor units (cents)**. `8368` = `$83.68`.

---

## Files to Modify

| # | File | Change |
|---|---|---|
| 1 | `database/migrations/` | New migration: add pricing columns to `sale_orders` and `sale_order_items` |
| 2 | `app/Models/SaleOrder.php` | Add new fillable fields and casts |
| 3 | `app/Models/SaleOrderItem.php` | Add new fillable fields and casts |
| 4 | `app/Http/Requests/SaleOrder/StoreSaleOrderRequest.php` | Add validation for new pricing fields |
| 5 | `app/Services/SaleOrderService.php` | Accept Manastore pricing instead of computing it |
| 6 | `app/Http/Resources/ManaStore/V1/SaleOrderResource.php` | Return new pricing fields |
| 7 | `app/Http/Resources/SaleOrderResource.php` | Return new pricing fields for Admin API |
| 8 | `app/Http/Resources/SaleOrderItemResource.php` | Return new pricing fields |
| 9 | `database/factories/SaleOrderFactory.php` | Add new fields to factory |
| 10 | `database/factories/SaleOrderItemFactory.php` | Add new fields to factory |
| 11 | Tests | Update existing tests, add new tests |

---

## Detailed Changes

### 1. Database Migration

**File:** `database/migrations/2026_04_02_000000_add_manastore_pricing_columns_to_sale_orders.php`

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('source');
            $table->bigInteger('subtotal')->default(0)->after('total_price');
            $table->bigInteger('conversion_fees')->default(0)->after('subtotal');
            $table->bigInteger('total')->default(0)->after('conversion_fees');
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            $table->string('variant_name')->nullable()->after('product_variant_id');
            $table->string('product_name')->nullable()->after('variant_name');
            $table->bigInteger('price')->default(0)->after('subtotal');
            $table->bigInteger('purchase_price')->default(0)->after('price');
            $table->bigInteger('conversion_fee')->default(0)->after('purchase_price');
            $table->bigInteger('total_price')->default(0)->after('conversion_fee');
            $table->bigInteger('discount_amount')->default(0)->after('total_price');
            $table->string('currency', 3)->default('USD')->after('discount_amount');
            $table->string('status')->default('pending')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'subtotal', 'conversion_fees', 'total']);
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_variant_id',
                'variant_name',
                'product_name',
                'price',
                'purchase_price',
                'conversion_fee',
                'total_price',
                'discount_amount',
                'currency',
                'status',
            ]);
        });
    }
};
```

> **Note:** The existing `sale_orders.total_price` (decimal) column is kept for backward compatibility. The new `total` (bigInteger/cents) column is the Manastore source of truth going forward. Similarly, `sale_order_items.unit_price` and `sale_order_items.subtotal` are kept; the new columns are the Manastore source of truth.

---

### 2. `SaleOrder` Model

**File:** `app/Models/SaleOrder.php`

Add new fillable fields and casts:

```php
protected $fillable = [
    'order_number',
    'source',
    'currency',
    'total_price',
    'subtotal',
    'conversion_fees',
    'total',
    'status',
];

protected $casts = [
    'total_price' => 'decimal:2',
    'subtotal' => 'integer',
    'conversion_fees' => 'integer',
    'total' => 'integer',
];
```

---

### 3. `SaleOrderItem` Model

**File:** `app/Models/SaleOrderItem.php`

Add new fillable fields and casts:

```php
protected $fillable = [
    'sale_order_id',
    'product_id',
    'product_variant_id',
    'variant_name',
    'product_name',
    'quantity',
    'unit_price',
    'subtotal',
    'price',
    'purchase_price',
    'conversion_fee',
    'total_price',
    'discount_amount',
    'currency',
    'status',
];

protected $casts = [
    'unit_price' => 'decimal:2',
    'subtotal' => 'decimal:2',
    'price' => 'integer',
    'purchase_price' => 'integer',
    'conversion_fee' => 'integer',
    'total_price' => 'integer',
    'discount_amount' => 'integer',
];
```

---

### 4. `StoreSaleOrderRequest` Validation

**File:** `app/Http/Requests/SaleOrder/StoreSaleOrderRequest.php`

Update `rules()` to accept the new Manastore pricing fields:

```php
public function rules(): array
{
    return [
        'order_number' => ['required', 'string', 'max:255', 'unique:sale_orders,order_number'],
        'source' => ['nullable', 'string', 'max:255'],
        'currency' => ['required', 'string', 'max:3'],
        'subtotal' => ['required', 'integer', 'min:0'],
        'conversion_fees' => ['required', 'integer', 'min:0'],
        'total' => ['required', 'integer', 'min:0'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
        'items.*.product_variant_id' => ['required', 'integer'],
        'items.*.variant_name' => ['required', 'string', 'max:255'],
        'items.*.product_name' => ['required', 'string', 'max:255'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
        'items.*.price' => ['required', 'integer', 'min:0'],
        'items.*.purchase_price' => ['required', 'integer', 'min:0'],
        'items.*.conversion_fee' => ['required', 'integer', 'min:0'],
        'items.*.total_price' => ['required', 'integer', 'min:0'],
        'items.*.discount_amount' => ['required', 'integer', 'min:0'],
        'items.*.currency' => ['required', 'string', 'max:3'],
    ];
}
```

Also update `messages()` and `attributes()` for the new fields.

---

### 5. `SaleOrderService::createOrder()` — Core Change

**File:** `app/Services/SaleOrderService.php`

**Before (current — calculates pricing from `$product->selling_price`):**

```php
foreach ($data['items'] as $itemData) {
    $product = $this->productRepository->getProductById($itemData['product_id']);
    $quantity = $itemData['quantity'];

    $unitPrice = $product->selling_price;          // ← Calculated locally
    $subtotal  = $quantity * $unitPrice;            // ← Calculated locally

    $item = $saleOrder->items()->create([
        'product_id' => $product->id,
        'quantity'   => $quantity,
        'unit_price' => $unitPrice,
        'subtotal'   => $subtotal,
    ]);
    // ...
    $totalPrice += $subtotal;
}

$saleOrder->update([
    'status'      => $finalStatus,
    'total_price' => $totalPrice,                   // ← Calculated locally
]);
```

**After (new — uses Manastore pricing as source of truth):**

```php
$saleOrder = $this->saleOrderRepository->createSaleOrder([
    'order_number'    => $data['order_number'],
    'source'          => SaleOrder::MANASTORE,
    'currency'        => $data['currency'],
    'subtotal'        => $data['subtotal'],
    'conversion_fees' => $data['conversion_fees'],
    'total'           => $data['total'],
    'total_price'     => $data['total'] / 100,      // Legacy column (decimal in dollars)
    'status'          => Status::PENDING->value,
]);

// ...

foreach ($data['items'] as $itemData) {
    $product = $this->productRepository->getProductById($itemData['product_id']);
    $quantity = $itemData['quantity'];

    $item = $saleOrder->items()->create([
        'product_id'         => $product->id,
        'product_variant_id' => $itemData['product_variant_id'],
        'variant_name'       => $itemData['variant_name'],
        'product_name'       => $itemData['product_name'],
        'quantity'           => $quantity,
        'unit_price'         => $itemData['price'] / 100,   // Legacy column (decimal)
        'subtotal'           => $itemData['total_price'] / 100, // Legacy column (decimal)
        'price'              => $itemData['price'],
        'purchase_price'     => $itemData['purchase_price'],
        'conversion_fee'     => $itemData['conversion_fee'],
        'total_price'        => $itemData['total_price'],
        'discount_amount'    => $itemData['discount_amount'],
        'currency'           => $itemData['currency'],
        'status'             => 'pending',
    ]);

    $allocated = $this->digitalProductAllocationService->allocate($item, $product, $quantity);

    if ($allocated < $quantity) {
        $fullyAllocated = false;
    }
}

// Update status only — pricing already set from Manastore
$saleOrder->update([
    'status' => $finalStatus,
]);
```

**Key differences:**
1. `unit_price` / `subtotal` / `total_price` no longer calculated from `$product->selling_price`
2. All monetary values come directly from `$data` (Manastore payload)
3. Legacy decimal columns populated via `/ 100` conversion for backward compatibility
4. New integer (cents) columns store exact Manastore values

---

### 6. ManaStore `SaleOrderResource`

**File:** `app/Http/Resources/ManaStore/V1/SaleOrderResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'order_number' => $this->order_number,
        'source' => $this->source,
        'currency' => $this->currency,
        'subtotal' => [
            'amount' => (string) $this->subtotal,
            'currency' => $this->currency,
        ],
        'conversion_fees' => [
            'amount' => (string) $this->conversion_fees,
            'currency' => $this->currency,
        ],
        'total' => [
            'amount' => (string) $this->total,
            'currency' => $this->currency,
        ],
        'total_price' => $this->total_price,
        'status' => $this->status,
        'created_at' => $this->created_at->toDateTimeString(),
        'updated_at' => $this->updated_at->toDateTimeString(),
        'items' => SaleOrderItemResource::collection($this->whenLoaded('items')),
    ];
}
```

---

### 7. ManaStore `SaleOrderItemResource` (New)

**File:** `app/Http/Resources/ManaStore/V1/SaleOrderItemResource.php`

Create a new resource for ManaStore item responses:

```php
<?php

namespace App\Http\Resources\ManaStore\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleOrderItem
 */
class SaleOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->sale_order_id,
            'product_variant_id' => $this->product_variant_id,
            'variant_name' => $this->variant_name,
            'product_name' => $this->product_name,
            'purchase_price' => [
                'amount' => (string) $this->purchase_price,
                'currency' => $this->currency,
            ],
            'price' => [
                'amount' => (string) $this->price,
                'currency' => $this->currency,
            ],
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'conversion_fee' => [
                'amount' => (string) $this->conversion_fee,
                'currency' => $this->currency,
            ],
            'total_price' => [
                'amount' => (string) $this->total_price,
                'currency' => $this->currency,
            ],
            'discount_amount' => [
                'amount' => (string) $this->discount_amount,
                'currency' => $this->currency,
            ],
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
```

---

### 8. Admin `SaleOrderResource`

**File:** `app/Http/Resources/SaleOrderResource.php`

Add new fields while keeping backward compatibility:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'order_number' => $this->order_number,
        'source' => $this->source,
        'currency' => $this->currency,
        'total_price' => $this->total_price,
        'subtotal' => $this->subtotal,
        'conversion_fees' => $this->conversion_fees,
        'total' => $this->total,
        'status' => $this->status,
        'created_at' => $this->created_at->toDateTimeString(),
        'updated_at' => $this->updated_at->toDateTimeString(),
        'items' => SaleOrderItemResource::collection($this->items),
    ];
}
```

---

### 9. Admin `SaleOrderItemResource`

**File:** `app/Http/Resources/SaleOrderItemResource.php`

Add new pricing fields:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'product_id' => $this->product_id,
        'product_variant_id' => $this->product_variant_id,
        'variant_name' => $this->variant_name,
        'product_name' => $this->product_name,
        'quantity' => $this->quantity,
        'unit_price' => $this->unit_price,
        'subtotal' => $this->subtotal,
        'price' => $this->price,
        'purchase_price' => $this->purchase_price,
        'conversion_fee' => $this->conversion_fee,
        'total_price' => $this->total_price,
        'discount_amount' => $this->discount_amount,
        'currency' => $this->currency,
        'status' => $this->status,
        'product' => new ProductResource($this->whenLoaded('product')),
    ];
}
```

---

### 10. Factory Updates

#### `SaleOrderFactory`

**File:** `database/factories/SaleOrderFactory.php`

```php
public function definition(): array
{
    self::$orderCounter++;

    return [
        'order_number' => 'SO-'.date('Y').'-'.str_pad(
            (string) self::$orderCounter,
            6,
            '0',
            STR_PAD_LEFT
        ),
        'source' => SaleOrder::MANASTORE,
        'currency' => 'USD',
        'total_price' => 0,
        'subtotal' => 0,
        'conversion_fees' => 0,
        'total' => 0,
        'status' => Status::PENDING->value,
    ];
}
```

#### `SaleOrderItemFactory`

**File:** `database/factories/SaleOrderItemFactory.php`

Add the new fields to the factory `definition()`:

```php
public function definition(): array
{
    return [
        'sale_order_id' => SaleOrder::factory(),
        'product_id' => Product::factory(),
        'product_variant_id' => null,
        'variant_name' => $this->faker->words(3, true),
        'product_name' => $this->faker->words(3, true),
        'quantity' => $this->faker->numberBetween(1, 10),
        'unit_price' => $this->faker->randomFloat(2, 1, 100),
        'subtotal' => $this->faker->randomFloat(2, 1, 1000),
        'price' => $this->faker->numberBetween(100, 10000),
        'purchase_price' => $this->faker->numberBetween(100, 10000),
        'conversion_fee' => 0,
        'total_price' => $this->faker->numberBetween(100, 10000),
        'discount_amount' => 0,
        'currency' => 'USD',
        'status' => 'pending',
    ];
}
```

---

## Data Flow Summary

### Before (Current)

```
Manastore                        Manavault
─────────                        ─────────
POST /sale-orders                 
  { order_number, items: [        
    { product_id, quantity }      → Fetches $product->selling_price
  ]}                              → Calculates unit_price & subtotal
                                  → Calculates total_price locally
                                  → Stores calculated values
```

### After (New)

```
Manastore                        Manavault
─────────                        ─────────
POST /sale-orders                 
  { order_number, currency,       
    subtotal, conversion_fees,    
    total, items: [               
    { product_id, quantity,       → Stores Manastore prices as-is
      price, purchase_price,      → No local price calculation
      conversion_fee, total_price,→ Legacy columns populated via /100
      discount_amount, currency,  
      product_variant_id,         
      variant_name, product_name  
    }                             
  ]}                              
```

---

## Backward Compatibility

- **Legacy columns preserved**: `sale_orders.total_price` (decimal), `sale_order_items.unit_price` (decimal), `sale_order_items.subtotal` (decimal) remain populated via `/ 100` conversion
- **Admin API** continues to return legacy fields alongside new fields
- **Existing tests** for admin `SaleOrderController` (index, show, filter) should still pass since the factory includes all fields
- **New integer columns** (`price`, `purchase_price`, `total_price`, etc.) store exact Manastore values in cents — no floating-point precision issues

---

## Testing Plan

### Updated Feature Tests — `SaleOrderService`

| Test | Change |
|---|---|
| `test_creates_pending_order_when_no_stock_available` | Update `createOrder()` payload to include pricing fields |
| `test_allows_order_when_sufficient_stock_available` | Update payload; assert stored prices match Manastore input |
| `test_creates_order_with_multiple_items` | Update payload; verify each item's Manastore pricing stored |
| `test_tracks_independent_stock_per_product` | Update payload with pricing |
| All auto-PO tests | Update payloads with pricing |

### New Feature Tests

| Test | Assertion |
|---|---|
| `test_stores_manastore_pricing_on_sale_order` | `sale_orders.subtotal`, `conversion_fees`, `total` match payload |
| `test_stores_manastore_item_pricing` | Each item's `price`, `purchase_price`, `total_price`, `discount_amount` match payload |
| `test_stores_currency_on_order_and_items` | `currency` field stored on both order and items |
| `test_stores_product_variant_metadata` | `product_variant_id`, `variant_name`, `product_name` stored |
| `test_legacy_columns_populated_from_cents` | `unit_price` = `price / 100`, `subtotal` = `total_price / 100` |
| `test_eur_order_stores_correct_currency` | EUR order stores `EUR` currency |

### Updated Controller Tests — `ManaStore/V1/SaleOrderControllerTest`

| Test | Change |
|---|---|
| `test_store_creates_sale_order_successfully` | Send full pricing payload; assert response includes pricing |
| `test_store_with_multiple_items` | Include pricing per item |
| `test_store_validates_*` | Add validation tests for new required fields |
| `test_store_calculates_correct_total_price` | Rename to `test_store_uses_manastore_pricing`; verify no local calculation |

### New Validation Tests

| Test | Assertion |
|---|---|
| `test_store_validates_currency_required` | 422 when `currency` missing |
| `test_store_validates_total_required` | 422 when `total` missing |
| `test_store_validates_item_price_required` | 422 when `items.*.price` missing |
| `test_store_validates_item_total_price_required` | 422 when `items.*.total_price` missing |
| `test_store_validates_product_variant_id_required` | 422 when `items.*.product_variant_id` missing |

---

## Migration Checklist

- [ ] Create migration with new columns (default values ensure existing rows unaffected)
- [ ] Update `SaleOrder` model ($fillable, $casts)
- [ ] Update `SaleOrderItem` model ($fillable, $casts)
- [ ] Update `StoreSaleOrderRequest` validation rules
- [ ] Update `SaleOrderService::createOrder()` to accept Manastore pricing
- [ ] Create `ManaStore/V1/SaleOrderItemResource`
- [ ] Update `ManaStore/V1/SaleOrderResource` to include new fields + items
- [ ] Update Admin `SaleOrderResource` with new fields
- [ ] Update Admin `SaleOrderItemResource` with new fields
- [ ] Update `SaleOrderFactory` with new fields
- [ ] Update `SaleOrderItemFactory` with new fields
- [ ] Update all `SaleOrderService` tests with new payload format
- [ ] Update all `ManaStore/V1/SaleOrderController` tests with new payload format
- [ ] Add new validation tests for required pricing fields
- [ ] Run `./vendor/bin/pint` (format)
- [ ] Run `./vendor/bin/phpstan analyse` (type check)
- [ ] Run `./vendor/bin/phpunit` (tests)
