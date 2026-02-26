# Gift2Games Multi-Currency (EUR/GBP) Wallet Support — Design Document

## Overview

Gift2Games currently operates with a single wallet (USD). Two new supplier entries have been added (`gift-2-games-eur` and `gift-2-games-gbp`) with separate API access tokens configured in `config/services.php`. The goal is to reuse the existing Gift2Games client/action/service code while routing API calls to the correct wallet (access token) based on the supplier's currency.

---

## Current Architecture

```
config/services.php
  └── gift2games           → GIFT_2_GAMES_ACCESS_TOKEN (USD)
  └── gift2games_eur       → GIFT_2_GAMES_EUR_ACCESS_TOKEN
  └── gift2games_gbp       → GIFT_2_GAMES_GBP_ACCESS_TOKEN

Client Layer (app/Clients/Gift2Games/)
  └── Client.php           → extends BaseApiClient, hardcoded config prefix "services.gift2games"
  └── Products.php         → extends Client, calls GET /products
  └── Order.php            → extends Client, calls POST /create_order

Actions (app/Actions/Gift2Games/)
  └── GetProducts.php      → injects Products client, calls fetchList()
  └── CreateOrder.php      → injects Order client, calls createOrder()

Services (app/Services/Gift2Games/)
  └── SyncDigitalProducts.php        → uses GetProducts action, hardcoded supplier slug "gift2games"
  └── Gift2GamesPlaceOrderService.php → uses CreateOrder action
  └── Gift2GamesVoucherService.php    → processes voucher responses

Repository (app/Repositories/PurchaseOrderRepository.php)
  └── placeExternalOrder()   → match on supplier slug, only "gift2games" recognized
  └── processPurchaseOrderItems() → voucher handling only for slug "gift2games"

Console Command
  └── SyncGift2GamesProductsCommand  → calls SyncDigitalProducts (USD wallet only)
```

### Problem

The `Client` class hardcodes `getConfigPrefix()` to return `'services.gift2games'`, so the same USD access token is always used regardless of which supplier entity is involved. The `PurchaseOrderRepository` only matches `'gift2games'` slug in its `placeExternalOrder()` and voucher-handling logic.

---

## Proposed Changes

### 1. Make `Gift2Games\Client` Accept a Dynamic Config Prefix

**File:** `app/Clients/Gift2Games/Client.php`

Add a constructor parameter for the config prefix so the client can be instantiated with different credentials:

```php
class Client extends BaseApiClient
{
    private string $configPrefixOverride;

    public function __construct(string $configPrefix = 'services.gift2games')
    {
        $this->configPrefixOverride = $configPrefix;
        parent::__construct();
    }

    protected function getConfigPrefix(): string
    {
        return $this->configPrefixOverride;
    }

    // ... rest stays the same
}
```

### 2. Make `Products` and `Order` Forward the Config Prefix

**File:** `app/Clients/Gift2Games/Products.php`

```php
class Products extends Client
{
    public function __construct(string $configPrefix = 'services.gift2games')
    {
        parent::__construct($configPrefix);
    }
    // ... fetchList() stays the same
}
```

**File:** `app/Clients/Gift2Games/Order.php`

```php
class Order extends Client
{
    public function __construct(string $configPrefix = 'services.gift2games')
    {
        parent::__construct($configPrefix);
    }
    // ... createOrder() stays the same
}
```

### 3. Create a Client Factory

**File (new):** `app/Factory/G2GClient/ClientFactory.php`

A factory that maps a supplier slug to the correct config prefix and instantiates the appropriate client:

```php
namespace App\Factory\G2GClient;

class ClientFactory
{
    private const SLUG_TO_CONFIG = [
        'gift2games'         => 'services.gift2games',
        'gift-2-games-eur'   => 'services.gift2games_eur',
        'gift-2-games-gbp'   => 'services.gift2games_gbp',
    ];

    public function getConfigPrefix(string $supplierSlug): string
    {
        return self::SLUG_TO_CONFIG[$supplierSlug]
            ?? throw new \InvalidArgumentException("Unknown Gift2Games supplier slug: {$supplierSlug}");
    }

    public function makeProductsClient(string $supplierSlug): Products
    {
        return new Products($this->getConfigPrefix($supplierSlug));
    }

    public function makeOrderClient(string $supplierSlug): Order
    {
        return new Order($this->getConfigPrefix($supplierSlug));
    }
}
```

### 4. Update Actions to Accept Supplier Slug

**File:** `app/Actions/Gift2Games/GetProducts.php`

Instead of injecting a single `Products` client, inject the factory and accept a slug:

```php
use App\Factory\G2GClient\ClientFactory;

class GetProducts
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(string $supplierSlug = 'gift2games'): array
    {
        $productsClient = $this->clientFactory->makeProductsClient($supplierSlug);
        return $productsClient->fetchList();
    }
}
```

**File:** `app/Actions/Gift2Games/CreateOrder.php`

```php
use App\Factory\G2GClient\ClientFactory;

class CreateOrder
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(array $orderData, string $supplierSlug = 'gift2games'): array
    {
        $orderClient = $this->clientFactory->makeOrderClient($supplierSlug);

        try {
            $orderResponse = $orderClient->createOrder($orderData);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return $orderResponse;
    }
}
```

### 5. Update `SyncDigitalProducts` Service

**File:** `app/Services/Gift2Games/SyncDigitalProducts.php`

Sync products for **all three** Gift2Games suppliers:

```php
class SyncDigitalProducts
{
    private const G2G_SUPPLIER_SLUGS = [
        'gift2games',
        'gift-2-games-eur',
        'gift-2-games-gbp',
    ];

    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        foreach (self::G2G_SUPPLIER_SLUGS as $slug) {
            $this->syncForSupplier($slug);
        }
    }

    private function syncForSupplier(string $supplierSlug): void
    {
        $supplier = Supplier::where('slug', $supplierSlug)->first();
        if (!$supplier) {
            Log::warning("Supplier not found for slug: {$supplierSlug}");
            return;
        }

        $products = $this->getProducts->execute($supplierSlug);
        if ($products['status'] == 0) {
            Log::error("Failed to sync Gift2Games products for supplier: {$supplierSlug}");
            return;
        }

        $items = $products['data'] ?? [];
        foreach ($items as $item) {
            $this->digitalProductRepository->createOrUpdate([
                'sku' => $item['id'],
                'supplier_id' => $supplier->id,
            ], [
                'supplier_id' => $supplier->id,
                'name' => $item['title'],
                'sku' => $item['id'],
                'brand' => $item['brand'] ?? null,
                'description' => $item['description'] ?? null,
                'cost_price' => $item['price'] ?? null,
                'currency' => strtolower($item['originalCurrency'] ?? 'usd'),
                'metadata' => $item,
                'source' => 'api',
                'last_synced_at' => now(),
            ]);
        }
    }
}
```

### 6. Update `Gift2GamesPlaceOrderService`

**File:** `app/Services/Gift2Games/Gift2GamesPlaceOrderService.php`

Accept a supplier slug and pass it through to the action:

```php
class Gift2GamesPlaceOrderService
{
    public function __construct(private CreateOrder $gift2GamesPlaceOrder) {}

    public function placeOrder(array $orderItems, string $orderNumber, string $supplierSlug = 'gift2games'): array
    {
        $vouchers = [];
        foreach ($orderItems as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $data = [
                    'productId' => (int) $item['digital_product']->sku,
                    'referenceNumber' => $orderNumber,
                ];
                try {
                    $response = $this->gift2GamesPlaceOrder->execute($data, $supplierSlug);
                } catch (\Exception $e) {
                    Log::error('Gift2Games Place Order Error: ' . $e->getMessage());
                    continue;
                }
                $voucherData = $response['data'];
                $voucherData['digital_product_id'] = $item['digital_product_id'];
                $vouchers[] = $voucherData;
            }
        }
        return $vouchers;
    }
}
```

### 7. Update `PurchaseOrderRepository`

**File:** `app/Repositories/PurchaseOrderRepository.php`

Add a helper to detect any Gift2Games supplier, and pass the slug through:

```php
// Add this helper method:
private function isGift2GamesSupplier(Supplier $supplier): bool
{
    return str_starts_with($supplier->slug, 'gift2games') 
        || str_starts_with($supplier->slug, 'gift-2-games');
}
```

**Update `placeExternalOrder()`:**

```php
private function placeExternalOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency): array
{
    if ($supplier->slug === 'ez_cards') {
        return $this->ezcardPlaceOrderService->placeOrder($orderItems, $orderNumber, $currency);
    }

    if ($this->isGift2GamesSupplier($supplier)) {
        return $this->gift2GamesPlaceOrderService->placeOrder($orderItems, $orderNumber, $supplier->slug);
    }

    throw new \RuntimeException("Unknown external supplier: {$supplier->slug}");
}
```

**Update `processPurchaseOrderItems()` — voucher handling block:**

Replace the slug check from:
```php
if ($supplier->slug === 'gift2games') {
```
To:
```php
if ($this->isGift2GamesSupplier($supplier)) {
```

This ensures all Gift2Games wallets (USD, EUR, GBP) get immediate voucher processing.

### 8. Update Tests

**File:** `tests/Unit/Actions/Gift2Games/CreateOrderTest.php`

Add test cases for EUR and GBP supplier slugs, verifying the correct access token / config is used.

**File:** `tests/Unit/Actions/Gift2Games/GetProductsTest.php`

Add test cases for fetching products with EUR and GBP supplier slugs.

---

## Files Changed Summary

| File | Change Type | Description |
|---|---|---|
| `app/Clients/Gift2Games/Client.php` | **Modified** | Accept dynamic config prefix via constructor |
| `app/Clients/Gift2Games/Products.php` | **Modified** | Forward config prefix to parent |
| `app/Clients/Gift2Games/Order.php` | **Modified** | Forward config prefix to parent |
| `app/Factory/G2GClient/ClientFactory.php` | **New** | Factory mapping supplier slugs → config prefixes |
| `app/Actions/Gift2Games/GetProducts.php` | **Modified** | Use factory, accept supplier slug |
| `app/Actions/Gift2Games/CreateOrder.php` | **Modified** | Use factory, accept supplier slug |
| `app/Services/Gift2Games/SyncDigitalProducts.php` | **Modified** | Loop over all G2G supplier slugs |
| `app/Services/Gift2Games/Gift2GamesPlaceOrderService.php` | **Modified** | Accept & forward supplier slug |
| `app/Repositories/PurchaseOrderRepository.php` | **Modified** | Recognize all G2G slugs for routing & vouchers |
| `tests/Unit/Actions/Gift2Games/CreateOrderTest.php` | **Modified** | Add EUR/GBP test cases |
| `tests/Unit/Actions/Gift2Games/GetProductsTest.php` | **Modified** | Add EUR/GBP test cases |

---

## Environment Variables Required

Add to `.env`:
```
GIFT_2_GAMES_EUR_ACCESS_TOKEN=<eur-wallet-token>
GIFT_2_GAMES_GBP_ACCESS_TOKEN=<gbp-wallet-token>
```

(Already configured in `config/services.php` — just need the actual values in `.env`)

---

## Key Design Decisions

1. **Factory pattern over service provider bindings** — Since we need to instantiate clients dynamically based on runtime supplier slug, a factory is cleaner than trying to register multiple named bindings in the container.

2. **Default parameter values** — All methods default to `'gift2games'` (USD) so existing code and tests that don't pass a slug continue to work without changes.

3. **`isGift2GamesSupplier()` helper** — Centralizes the slug-matching logic so new G2G currencies can be added by only updating the factory's `SLUG_TO_CONFIG` map and the seeder.

4. **No changes to `Gift2GamesVoucherService`** — The voucher processing logic is supplier-agnostic (it just processes response data), so no changes needed there.

5. **`BaseApiClient` untouched** — The base class constructor reads from `getConfigPrefix()`, which we override dynamically. No modifications needed to the base class.
