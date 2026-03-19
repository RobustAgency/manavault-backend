# Price Automation Migration: Product → Digital Product

> **Date**: March 18, 2026  
> **Branch**: `feature/move-price-automation-to-digital-product`  
> **Status**: Implementation Plan (Updated)  
> **Priority**: High  
> **Last Updated**: March 18, 2026 — Added `face_value` migration from Product → DigitalProduct

---

## 1. Background & Motivation

The current **Price Rule Automation** system applies pricing rules (percentage/absolute adjustments) to **Products**. However, with the architectural evolution of Manavault:

- **`selling_price` has been removed from the `products` table** (migration: `2026_03_06_110931_remove_selling_price_from_products_table.php`).
- **`selling_price` was added to the `digital_products` table** (migration: `2026_03_06_080813_add_selling_price_to_digital_products_table.php`).
- **`face_value` must also move from the `products` table to `digital_products`** — it is the base value for price rule calculations and belongs at the digital product level where pricing is managed.
- The `Product` model now derives its `selling_price` via an **accessor** (`getSellingPriceAttribute()`) that fetches the selling price from the highest-priority (or cheapest) associated digital product.
- **Products no longer own their selling price** — it's a computed value from digital products.

Applying price rules at the Product level no longer makes sense because:
1. Products don't store a selling price — it's derived from digital products.
2. Price rules currently write to `price_rule_product`, but there's no column on the product to persist the result.
3. The `face_value` used as the calculation base exists on Products, but should live on Digital Products where pricing is managed — the actual price consumers see is the digital product's `selling_price`.
4. Updating a digital product's selling price directly is the correct approach since that's the source of truth for pricing.
5. `face_value` on Digital Products serves as the stable base for price rule calculations (markup/markdown from face value).

---

## 2. Current Architecture (AS-IS)

### 2.1 Data Flow

```
PriceRule (conditions: brand_id, selling_price, etc.)
    ↓ matches
Product (face_value used as base)
    ↓ writes to
price_rule_product (stores: original_selling_price, base_value, calculated_price, final_selling_price)
    ↓ but Product::getSellingPriceAttribute() ignores this — returns DigitalProduct.selling_price
```

### 2.2 Affected Files

| File | Role |
|------|------|
| `app/Services/PricingRuleService.php` | Core service — creates/updates/previews price rules, applies actions to **Products** |
| `app/Models/PriceRuleProduct.php` | Pivot model — links `price_rule_id` ↔ `product_id` |
| `app/Models/Product.php` | Has `priceRuleProducts()` relationship, `getSellingPriceAttribute()` accessor, owns `face_value` |
| `app/Models/DigitalProduct.php` | Target for `face_value` migration — will own `face_value`, `cost_price`, `selling_price` |
| `app/Models/PriceRule.php` | Price rule model with `priceRuleProducts()` HasMany |
| `app/Models/PriceRuleCondition.php` | Condition model (field/operator/value) |
| `app/Repositories/ProductRepository.php` | `getProductsByConditions()` — queries Products by conditions |
| `app/Repositories/PriceRuleProductRepository.php` | CRUD for `price_rule_product` records |
| `app/Http/Controllers/Admin/PriceRuleController.php` | API endpoints for price rules |
| `app/Http/Requests/DigitalProduct/StoreDigitalProductRequest.php` | Validation for creating digital products — `face_value` to be added as **required** |
| `app/Http/Requests/DigitalProduct/UpdateDigitalProductRequest.php` | Validation for updating digital products — `face_value` to be added as optional |
| `app/Http/Resources/PriceRuleProductResource.php` | API resource — references `product_id`, `product` |
| `database/migrations/2026_02_24_100000_create_price_rule_product_table.php` | Migration — FK to `products` |
| `database/migrations/2025_12_11_100550_add_face_value_to_products_table.php` | Original migration that added `face_value` to `products` |
| `database/factories/PriceRuleProductFactory.php` | Factory — creates `product_id` |
| `tests/Feature/Services/PricingRuleServiceTest.php` | 15+ tests against Product-based pricing |
| `tests/Feature/Controllers/Admin/PriceRuleControllerTest.php` | Controller tests for price rules |
| `tests/Feature/Repositories/PriceRuleProductRepositoryTest.php` | Repository tests |

### 2.3 Current Calculation Logic

```php
// PricingRuleService::buildApplicationData()
$baseValue = (float) $product->face_value;
$originalSellingPrice = (float) $product->selling_price;
$calculatedPrice = $this->calculateNewPrice($product, ...);

// PricingRuleService::calculateNewPrice()
$base = (float) $product->face_value;
// PERCENTAGE: $base + ($base * ($actionValue / 100)) * (±1)
// ABSOLUTE:   $base + ($actionValue * (±1))
```

**Problem**: `$product->selling_price` is now a computed accessor that returns a digital product's selling price. The price rule result is never persisted back to any column — it's only stored in the `price_rule_product` pivot but nothing reads it for actual pricing. Additionally, `face_value` lives on Product but pricing is managed at the DigitalProduct level — `face_value` must move to `digital_products` to be the correct base for calculations.

---

## 3. Target Architecture (TO-BE)

### 3.1 New Data Flow

```
PriceRule (conditions: brand, supplier_id, cost_price range, currency, etc.)
    ↓ matches
DigitalProduct (face_value used as base for calculation)
    ↓ writes to
price_rule_digital_product (stores: original_selling_price, base_value, calculated_price, final_selling_price)
    ↓ AND updates
digital_products.selling_price = final_selling_price
    ↓ which is read by
Product::getSellingPriceAttribute() → returns updated selling_price from DigitalProduct
```

### 3.2 Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Base value = `digital_products.face_value`** | `face_value` is the true base/denomination value for a digital product (e.g., a $50 gift card has face_value=50). Price rules calculate selling price as a markup/markdown from face value. `face_value` moves from Product to DigitalProduct as part of this migration. |
| **`face_value` is mandatory on DigitalProduct** | Required in `StoreDigitalProductRequest` — every digital product must have a face value to serve as the base for price rule calculations and business reporting. |
| **Conditions target `digital_products` columns** | Conditions should filter on `supplier_id`, `brand`, `cost_price`, `face_value`, `currency`, `region` — all columns that exist on `digital_products`. |
| **Persist `final_selling_price` to `digital_products.selling_price`** | Unlike the current system, the result should actually update the digital product's selling price so consumers see the change. |
| **Keep audit trail in `price_rule_digital_product`** | Maintains full history of what was applied, original price, calculated price, etc. |
| **Product-level conditions (e.g., `brand_id`) resolved via relationship** | If a condition targets a Product-level field (like `brand_id`), resolve the matching Products first, then find their associated DigitalProducts. |

---

## 4. Implementation Plan

### Phase 1: Database Migration

#### 4.1.1 Add `face_value` to `digital_products` table

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_face_value_to_digital_products_table.php`

```php
Schema::table('digital_products', function (Blueprint $table) {
    $table->decimal('face_value', 10, 2)->after('cost_price');
});
```

> **Note**: `face_value` is NOT nullable — it is mandatory for all digital products. Existing records must be backfilled before running this migration (or add as nullable first, backfill, then alter to NOT NULL).

#### 4.1.2 Remove `face_value` from `products` table (deferred)

> **Note**: Do NOT remove immediately. Keep `face_value` on `products` during a transition period. Once all consumers read `face_value` from `digital_products`, add a migration to drop the column from `products`.

#### 4.1.3 Create `price_rule_digital_product` table

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_price_rule_digital_product_table.php`

```php
Schema::create('price_rule_digital_product', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(DigitalProduct::class)->constrained()->onDelete('cascade');
    $table->foreignIdFor(PriceRule::class)->constrained()->onDelete('cascade');
    $table->decimal('original_selling_price', 10, 2);
    $table->decimal('base_value', 10, 2);            // face_value at time of application
    $table->string('action_mode');                     // 'percentage' | 'absolute'
    $table->string('action_operator');                 // '+' | '-' | '='
    $table->decimal('action_value', 10, 2);
    $table->decimal('calculated_price', 10, 2);
    $table->decimal('final_selling_price', 10, 2);
    $table->timestamp('applied_at');
    $table->timestamps();
});
```

#### 4.1.2 Drop `price_rule_product` table (deferred)

> **Note**: Do NOT drop immediately. Keep the old table during a transition period for data reference. Add a migration to drop it in a future release after confirming no dependencies.

---

### Phase 2: New Model — `PriceRuleDigitalProduct`

**File**: `app/Models/PriceRuleDigitalProduct.php`

```php
class PriceRuleDigitalProduct extends Model
{
    protected $table = 'price_rule_digital_product';

    protected $fillable = [
        'digital_product_id',
        'price_rule_id',
        'original_selling_price',
        'base_value',
        'action_mode',
        'action_operator',
        'action_value',
        'calculated_price',
        'final_selling_price',
        'applied_at',
    ];

    public function digitalProduct(): BelongsTo { ... }
    public function priceRule(): BelongsTo { ... }
}
```

**File**: `database/factories/PriceRuleDigitalProductFactory.php` — mirror of current `PriceRuleProductFactory` but with `digital_product_id`.

---

### Phase 3: Update `DigitalProduct` Model

**File**: `app/Models/DigitalProduct.php`

Add `face_value` to `$fillable` and `$casts`:

```php
protected $fillable = [
    // ...existing fields...
    'face_value',    // NEW — moved from Product
];

protected $casts = [
    // ...existing casts...
    'face_value' => 'decimal:2',    // NEW
];
```

Add relationship:

```php
public function priceRuleDigitalProducts(): HasMany
{
    return $this->hasMany(PriceRuleDigitalProduct::class);
}
```

#### 3.1 Update `StoreDigitalProductRequest`

**File**: `app/Http/Requests/DigitalProduct/StoreDigitalProductRequest.php`

Add `face_value` as a **required** validation rule:

```diff
  public function rules(): array
  {
      return [
          'products' => ['required', 'array', 'min:1'],
          'products.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
          'products.*.name' => ['required', 'string', 'max:255'],
          'products.*.sku' => ['required', 'string', 'max:255'],
          'products.*.brand' => ['nullable', 'string', 'max:255'],
          'products.*.description' => ['nullable', 'string'],
+         'products.*.face_value' => ['required', 'numeric', 'gt:0'],
          'products.*.cost_price' => ['required', 'numeric', 'min:0'],
          'products.*.selling_price' => ['required', 'numeric', 'gt:0'],
          // ...
      ];
  }
```

Add custom validation message:

```diff
  public function messages(): array
  {
      return [
          // ...existing messages...
+         'products.*.face_value.required' => 'Face value is required for all products.',
+         'products.*.face_value.numeric' => 'Face value must be a number.',
+         'products.*.face_value.gt' => 'Face value must be greater than 0.',
      ];
  }
```

#### 3.2 Update `UpdateDigitalProductRequest`

**File**: `app/Http/Requests/DigitalProduct/UpdateDigitalProductRequest.php`

Add `face_value` as an optional update field:

```diff
  public function rules(): array
  {
      return [
+         'face_value' => ['sometimes', 'numeric', 'gt:0'],
          'cost_price' => ['sometimes', 'numeric', 'min:0'],
          'selling_price' => ['sometimes', 'numeric', 'gt:0'],
          // ...
      ];
  }
```

---

### Phase 4: New Repository — `PriceRuleDigitalProductRepository`

**File**: `app/Repositories/PriceRuleDigitalProductRepository.php`

Mirror `PriceRuleProductRepository` but operates on `PriceRuleDigitalProduct`:

| Method | Description |
|--------|-------------|
| `create(array $data)` | Create a new application record |
| `deleteByPriceRuleId(int $priceRuleId)` | Delete all records for a price rule |
| `getByPriceRule(int $priceRuleId, int $perPage)` | Paginated records for a price rule |
| `getByDigitalProduct(int $digitalProductId, int $perPage)` | Paginated records for a digital product |
| `getFilteredPriceRuleDigitalProducts(array $filters)` | Filtered/paginated records |
| `findById(int $id)` | Find single record |

---

### Phase 5: New Repository Method — `DigitalProductRepository::getDigitalProductsByConditions()`

**File**: `app/Repositories/DigitalProductRepository.php`

Add a method similar to `ProductRepository::getProductsByConditions()` but queries the `digital_products` table:

```php
public function getDigitalProductsByConditions(array $conditions, string $matchType = 'all'): Collection
{
    $query = DigitalProduct::query();

    // Supported condition fields on digital_products:
    // supplier_id, brand, cost_price, face_value, selling_price, currency, region, name, sku

    // For product-level fields (e.g., brand_id):
    // Resolve via product_supplier join to find matching digital products

    // Apply match_type: 'all' (AND) or 'any' (OR)

    return $query->get();
}
```

**Condition field mapping**:

| Condition Field | Table/Column | Notes |
|----------------|-------------|-------|
| `supplier_id` | `digital_products.supplier_id` | Direct |
| `brand` | `digital_products.brand` | Direct (string match) |
| `face_value` | `digital_products.face_value` | Direct — base value for price calculations |
| `cost_price` | `digital_products.cost_price` | Direct |
| `selling_price` | `digital_products.selling_price` | Direct |
| `currency` | `digital_products.currency` | Direct |
| `region` | `digital_products.region` | Direct |
| `brand_id` | Resolve via `product_supplier` → `products.brand_id` | Cross-table |
| `brand_name` | Resolve Brand by name → `brand_id` → products → digital products | Cross-table |

---

### Phase 6: Refactor `PricingRuleService`

**File**: `app/Services/PricingRuleService.php`

#### 6.1 Constructor Changes

```php
public function __construct(
    private PriceRuleRepository $priceRuleRepository,
    private PriceRuleConditionRepository $priceRuleConditionRepository,
    private PriceRuleDigitalProductRepository $priceRuleDigitalProductRepository,  // NEW
    private DigitalProductRepository $digitalProductRepository,                     // CHANGED
) {}
```

#### 6.2 `createPriceRuleWithConditions()` Changes

```diff
- $products = $this->productRepository->getProductsByConditions(...);
- foreach ($products as $product) {
-     $this->applyAction($product, $priceRule);
- }
+ $digitalProducts = $this->digitalProductRepository->getDigitalProductsByConditions(...);
+ foreach ($digitalProducts as $digitalProduct) {
+     $this->applyAction($digitalProduct, $priceRule);
+ }
```

#### 6.3 `applyAction()` Changes

```diff
- private function applyAction(Product $product, PriceRule $rule): void
+ private function applyAction(DigitalProduct $digitalProduct, PriceRule $rule): void
{
    $applicationData = $this->buildApplicationData(
-       $product,
+       $digitalProduct,
        $rule->action_mode,
        $rule->action_operator,
        $rule->action_value,
    );

-   $this->priceRuleProductRepository->create([
-       'product_id' => $applicationData['product_id'],
+   $this->priceRuleDigitalProductRepository->create([
+       'digital_product_id' => $applicationData['digital_product_id'],
        'price_rule_id' => $rule->id,
        ...
    ]);
+
+   // Persist the final selling price to the digital product
+   $digitalProduct->update(['selling_price' => $applicationData['final_selling_price']]);
}
```

#### 6.4 `buildApplicationData()` Changes

```diff
  private function buildApplicationData(
-     Product $product,
+     DigitalProduct $digitalProduct,
      string $actionMode,
      string $actionOperator,
      mixed $actionValue,
  ): array {
-     $baseValue = (float) $product->face_value;
-     $originalSellingPrice = (float) $product->selling_price;
+     $baseValue = (float) $digitalProduct->face_value;
+     $originalSellingPrice = (float) $digitalProduct->selling_price;
-     $calculatedPrice = $this->calculateNewPrice($product, ...);
+     $calculatedPrice = $this->calculateNewPrice($digitalProduct, ...);
      $finalSellingPrice = (float) max($calculatedPrice, 0);

      return [
-         'product_id' => $product->id,
-         'product_name' => $product->name,
+         'digital_product_id' => $digitalProduct->id,
+         'digital_product_name' => $digitalProduct->name,
          'original_selling_price' => $originalSellingPrice,
          'base_value' => $baseValue,
          ...
      ];
  }
```

#### 6.5 `calculateNewPrice()` Changes

```diff
- private function calculateNewPrice(Product $product, ...): float
+ private function calculateNewPrice(DigitalProduct $digitalProduct, ...): float
  {
-     $base = (float) $product->face_value;
+     $base = (float) $digitalProduct->face_value;
      // rest stays the same
  }
```

#### 6.6 `previewPriceRuleEffect()` Changes

```diff
  public function previewPriceRuleEffect(array $data): array
  {
-     $products = $this->productRepository->getProductsByConditions(...);
+     $digitalProducts = $this->digitalProductRepository->getDigitalProductsByConditions(...);

      $preview = [];
-     foreach ($products as $product) {
+     foreach ($digitalProducts as $digitalProduct) {
          $applicationData = $this->buildApplicationData(
-             $product,
+             $digitalProduct,
              ...
          );

          $preview[] = [
-             'product_id' => $applicationData['product_id'],
-             'product_name' => $applicationData['product_name'],
-             'face_value' => $applicationData['face_value'],
+             'digital_product_id' => $applicationData['digital_product_id'],
+             'digital_product_name' => $applicationData['digital_product_name'],
+             'face_value' => $applicationData['base_value'],
              'current_selling_price' => $applicationData['current_selling_price'],
              'new_selling_price' => $applicationData['new_selling_price'],
          ];
      }

      return $preview;
  }
```

---

### Phase 7: Update API Resource

**File**: `app/Http/Resources/PriceRuleDigitalProductResource.php` (NEW)

```php
class PriceRuleDigitalProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'digital_product_id' => $this->digital_product_id,
            'price_rule_id' => $this->price_rule_id,
            'original_selling_price' => $this->original_selling_price,
            'base_value' => $this->base_value,
            'action_mode' => $this->action_mode,
            'action_operator' => $this->action_operator,
            'action_value' => $this->action_value,
            'calculated_price' => $this->calculated_price,
            'final_selling_price' => $this->final_selling_price,
            'applied_at' => $this->applied_at,
            'digital_product' => new DigitalProductResource($this->whenLoaded('digitalProduct')),
            'price_rule' => new PriceRuleResource($this->whenLoaded('priceRule')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

---

### Phase 8: Update Controller

**File**: `app/Http/Controllers/Admin/PriceRuleController.php`

Update `postViewProduct()` → rename to `postViewDigitalProducts()`:

```diff
- public function postViewProduct(PriceRule $priceRule): JsonResponse
+ public function postViewDigitalProducts(PriceRule $priceRule): JsonResponse
  {
-     $products = $this->priceRuleProductRepository->getByPriceRule($priceRule->id);
+     $digitalProducts = $this->priceRuleDigitalProductRepository->getByPriceRule($priceRule->id);

      return response()->json([
          'error' => false,
-         'data' => $products,
-         'message' => 'Price rule products retrieved successfully.',
+         'data' => $digitalProducts,
+         'message' => 'Price rule digital products retrieved successfully.',
      ]);
  }
```

Update constructor:

```diff
  public function __construct(
      private PricingRuleService $pricingRuleService,
      private PriceRuleRepository $priceRuleRepository,
-     private PriceRuleProductRepository $priceRuleProductRepository,
+     private PriceRuleDigitalProductRepository $priceRuleDigitalProductRepository,
  ) {}
```

---

### Phase 9: Update Routes

**File**: `routes/users/api.php`

```diff
  Route::prefix('/price-rules')->controller(PriceRuleController::class)->group(function () {
      // ... existing routes ...
-     Route::get('/{priceRule}/products', 'postViewProduct')->middleware('permission:view_price_rule');
+     Route::get('/{priceRule}/digital-products', 'postViewDigitalProducts')->middleware('permission:view_price_rule');
  });
```

> **API Versioning Note**: If there are external consumers of `/{priceRule}/products`, keep the old route as a deprecated alias during a transition period.

---

### Phase 10: Update `Product` Model

**File**: `app/Models/Product.php`

Remove the `priceRuleProducts()` relationship (no longer needed):

```diff
- /**
-  * Get the price rule applications for this product.
-  *
-  * @return HasMany<PriceRuleProduct, $this>
-  */
- public function priceRuleProducts(): HasMany
- {
-     return $this->hasMany(PriceRuleProduct::class);
- }
```

The `getSellingPriceAttribute()` accessor **stays as-is** — it already reads from `DigitalProduct.selling_price`, which will now be updated by the price rule automation.

---

### Phase 11: Update/Rewrite Tests

#### Files to Update:

| Test File | Changes |
|-----------|---------|
| `tests/Feature/Services/PricingRuleServiceTest.php` | Replace all `Product::factory()->create(['selling_price' => X])` with `DigitalProduct::factory()->create(['face_value' => X, ...])`. Update assertions to check `price_rule_digital_product` table and `digital_products.selling_price`. Base value assertions should use `face_value`. |
| `tests/Feature/Controllers/Admin/PriceRuleControllerTest.php` | Update endpoint tests for `/digital-products`. Update assertions for response structure (`digital_product_id` instead of `product_id`). |
| `tests/Feature/Controllers/Admin/DigitalProductControllerTest.php` | Add validation tests: creating a digital product without `face_value` returns 422. |
| `tests/Feature/Repositories/PriceRuleProductRepositoryTest.php` | Rename/replace with `PriceRuleDigitalProductRepositoryTest`. |

#### Key Test Scenarios:

1. **Create price rule → applies to matching digital products**
2. **Update price rule → re-applies to matching digital products, clears stale records**
3. **Preview → returns digital product IDs with calculated prices**
4. **Percentage addition/subtraction on digital product face_value**
5. **Absolute addition/subtraction on digital product face_value**
6. **Multiple conditions (all/any match types)**
7. **Conditions filtering by `supplier_id`, `brand`, `currency`, `region`, `face_value`, `cost_price`**
8. **Product-level condition (e.g., `brand_id`) resolves via relationship**
9. **Digital product `selling_price` actually updated after rule application**
10. **Inactive rules do not affect digital product selling price**
11. **Product accessor returns updated selling price after rule application**
12. **Creating a digital product without `face_value` fails validation**
13. **`face_value` is correctly used as the base for price rule calculations (not `cost_price`)**

---

## 5. Condition Field Migration Guide

### Current Condition Fields (Product-level)

| Field | Table |
|-------|-------|
| `brand_id` | `products.brand_id` |
| `selling_price` | `products.selling_price` (REMOVED) |
| `face_value` | `products.face_value` (MOVING to `digital_products`) |
| `currency` | `products.currency` |

### New Condition Fields (Digital Product-level)

| Field | Table | Notes |
|-------|-------|-------|
| `supplier_id` | `digital_products.supplier_id` | NEW — filter by supplier |
| `brand` | `digital_products.brand` | String field on digital products |
| `face_value` | `digital_products.face_value` | Moved from `products` — base value for price calculations |
| `cost_price` | `digital_products.cost_price` | Supplier's cost for the digital product |
| `selling_price` | `digital_products.selling_price` | Now on digital products |
| `currency` | `digital_products.currency` | Same concept, different table |
| `region` | `digital_products.region` | NEW — filter by region |
| `brand_id` | Resolved via `product_supplier` → `products.brand_id` | Cross-table lookup |

---

## 6. Rollback Strategy

1. Keep `price_rule_product` table intact during transition.
2. Keep `face_value` on `products` table during transition (do not drop yet).
3. Keep `PriceRuleProduct` model and repository in codebase but unused.
4. If rollback needed, revert service to use `ProductRepository` and `PriceRuleProductRepository`, and revert `face_value` base to `products.face_value`.
5. Add a feature flag (optional) to toggle between product-level and digital-product-level automation.

---

## 7. File Change Summary

### New Files

| File | Type |
|------|------|
| `database/migrations/XXXX_add_face_value_to_digital_products_table.php` | Migration |
| `database/migrations/XXXX_create_price_rule_digital_product_table.php` | Migration |
| `app/Models/PriceRuleDigitalProduct.php` | Model |
| `database/factories/PriceRuleDigitalProductFactory.php` | Factory |
| `app/Repositories/PriceRuleDigitalProductRepository.php` | Repository |
| `app/Http/Resources/PriceRuleDigitalProductResource.php` | Resource |
| `tests/Feature/Repositories/PriceRuleDigitalProductRepositoryTest.php` | Test |

### Modified Files

| File | Change |
|------|--------|
| `app/Models/DigitalProduct.php` | Add `face_value` to `$fillable` and `$casts`, add `priceRuleDigitalProducts()` relationship |
| `app/Http/Requests/DigitalProduct/StoreDigitalProductRequest.php` | Add `face_value` as **required** validation rule |
| `app/Http/Requests/DigitalProduct/UpdateDigitalProductRequest.php` | Add `face_value` as optional validation rule |
| `app/Services/PricingRuleService.php` | Refactor to operate on DigitalProduct instead of Product, use `face_value` as base |
| `app/Repositories/DigitalProductRepository.php` | Add `getDigitalProductsByConditions()` |
| `app/Models/Product.php` | Remove `priceRuleProducts()` relationship (keep `face_value` during transition) |
| `app/Http/Controllers/Admin/PriceRuleController.php` | Update DI and method for digital products |
| `routes/users/api.php` | Update route from `/products` to `/digital-products` |
| `tests/Feature/Services/PricingRuleServiceTest.php` | Full rewrite |
| `tests/Feature/Controllers/Admin/PriceRuleControllerTest.php` | Update for new structure |

### Deprecated (Remove Later)

| File | Action |
|------|--------|
| `app/Models/PriceRuleProduct.php` | Deprecate, remove after transition |
| `app/Repositories/PriceRuleProductRepository.php` | Deprecate, remove after transition |
| `app/Http/Resources/PriceRuleProductResource.php` | Deprecate, remove after transition |
| `database/factories/PriceRuleProductFactory.php` | Deprecate, remove after transition |
| `tests/Feature/Repositories/PriceRuleProductRepositoryTest.php` | Remove |
| `database/migrations/2026_02_24_100000_create_price_rule_product_table.php` | Drop table migration later |

---

## 8. Migration Order (Recommended)

1. ✅ Add `face_value` to `digital_products` migration and run it
2. ✅ Create `price_rule_digital_product` migration and run it
3. ✅ Create `PriceRuleDigitalProduct` model + factory
4. ✅ Create `PriceRuleDigitalProductRepository`
5. ✅ Add `face_value` to `DigitalProduct` model (`$fillable`, `$casts`)
6. ✅ Update `StoreDigitalProductRequest` — add `face_value` as **required**
7. ✅ Update `UpdateDigitalProductRequest` — add `face_value` as optional
8. ✅ Add `getDigitalProductsByConditions()` to `DigitalProductRepository`
9. ✅ Add `priceRuleDigitalProducts()` to `DigitalProduct` model
10. ✅ Create `PriceRuleDigitalProductResource`
11. ✅ Refactor `PricingRuleService` (core logic change — use `face_value` as base)
12. ✅ Update `PriceRuleController` (DI + method rename)
13. ✅ Update routes
14. ✅ Remove `priceRuleProducts()` from `Product` model
15. ✅ Rewrite all tests
16. ✅ Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/phpunit`
17. 🔜 (Future) Remove `face_value` from `products` table
18. 🔜 (Future) Drop `price_rule_product` table + remove deprecated files

---

## 9. API Contract Changes

### Before

```
GET /api/price-rules/{priceRule}/products

Response:
{
  "data": {
    "data": [{
      "product_id": 1,
      "price_rule_id": 1,
      "original_selling_price": "100.00",
      "final_selling_price": "90.00",
      "product": { ... }
    }]
  }
}
```

### After

```
GET /api/price-rules/{priceRule}/digital-products

Response:
{
  "data": {
    "data": [{
      "digital_product_id": 5,
      "price_rule_id": 1,
      "original_selling_price": "80.00",
      "final_selling_price": "72.00",
      "digital_product": { ... }
    }]
  }
}
```

### Preview Endpoint Changes

```
POST /api/price-rules/preview

Response Before:
[{ "product_id": 1, "product_name": "...", "face_value": 100, "current_selling_price": 100, "new_selling_price": 90 }]

Response After:
[{ "digital_product_id": 5, "digital_product_name": "...", "face_value": 80, "current_selling_price": 80, "new_selling_price": 72 }]
```

---

## 10. Open Questions

| # | Question | Recommendation |
|---|----------|----------------|
| 1 | Should price rules **persist** the calculated selling price to `digital_products.selling_price`? | **Yes** — this is the source of truth for pricing. Without persisting, the rule has no effect. |
| 2 | Should we support product-level conditions (e.g., `brand_id`) that resolve to digital products via the `product_supplier` pivot? | **Yes** — brand is a common business condition. Resolve Products first, then find associated DigitalProducts. |
| 3 | Should price rule application be idempotent (re-running doesn't stack)? | **Yes** — `deleteByPriceRuleId()` before re-applying ensures idempotency. Current pattern already does this. |
| 4 | What happens if a digital product is assigned to multiple products? | The price rule applies to the digital product once; all parent products see the updated selling price via the accessor. |
| 5 | Should we add a `face_value` column to `digital_products`? | **Yes (Decided)** — `face_value` moves from `products` to `digital_products`. It is **mandatory** when creating a digital product via `StoreDigitalProductRequest`. It serves as the base value for price rule calculations (replacing `cost_price` as the base). `face_value` on `products` will be kept during a transition period and removed in a future migration. |
