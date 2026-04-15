# Gamezcode Supplier Integration (Kalixo API)

## Overview

This document outlines the integration plan for the **Gamezcode** supplier using the [Kalixo API](https://developer.kalixo.io). The integration follows the same pattern as the existing **Giftery** client and includes:

- Database seeder for the supplier record
- API client covering all required endpoints

---

## Base URL

```
https://api.kalixo.io/v1
```

---

## Authentication

Kalixo uses a **JWT-based auth** flow with short-lived access tokens and longer-lived refresh tokens.

### 1. Login

**POST** `/auth/login`

Authenticates with email and password and returns an `accessToken` and `refreshToken`.

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john.doe@email.com",
    "password": "zpVY8eV!wk"
  }' \
  https://api.kalixo.io/v1/auth/login
```

**Response:**

```json
{
  "accessToken": "<JWT_ACCESS_TOKEN>",
  "refreshToken": "<JWT_REFRESH_TOKEN>"
}
```

> **Notes:**
> - The `accessToken` is short-lived and used in all subsequent API calls via `Authorization: Bearer <ACCESS_TOKEN>`.
> - The `refreshToken` has a longer TTL and is used to obtain a new access token without re-login.

---

### 2. Refresh Token

**POST** `/auth/refresh`

Uses the `refreshToken` to obtain a new `accessToken` (and optionally a new `refreshToken`).

```bash
curl -X POST \
  -H "Authorization: Bearer <REFRESH_TOKEN>" \
  https://api.kalixo.io/v1/auth/refresh
```

**Response:** Same structure as the Login response.

> **Implementation note:** The client should automatically detect `401` responses and attempt a token refresh before retrying the original request. If the refresh also fails, re-authenticate using stored credentials.

---

## Catalog / Products

### 3. Product List

**GET** `/catalog/products`

Returns a paginated list of products available in the reseller's catalog.

```bash
curl -X GET \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  "https://api.kalixo.io/v1/catalog/products?skip=1&take=30"
```

**Query Parameters:**

| Parameter | Type    | Required | Description                          |
|-----------|---------|----------|--------------------------------------|
| `skip`    | integer | No       | Number of products to skip (offset)  |
| `take`    | integer | No       | Number of products to return (max 100) |

**Response:**

```json
{
  "count": 2083,
  "take": 30,
  "skip": 1,
  "products": [
    {
      "id": 1,
      "ean": "4251604177254",
      "name": "Xbox Game Pass Ultimate 1 month",
      "shortName": "Microsoft Xbox Game Pass Ult 1M ESD UK",
      "image": "https://cdn.kalixo.io/static-images/GB-EN-4251604177254.png",
      "rrp": "10.99",
      "rrpCurrency": "GBP",
      "price": 1099,
      "currencyCode": "GBP",
      "buyingPrice": 959,
      "buyingCurrencyCode": "GBP",
      "isCurrencyMismatch": false,
      "brand": "Microsoft",
      "publisher": "Microsoft Xbox ESD (UK)",
      "countryCode": "GB",
      "denominationType": "fixed",
      "mainCategory": "Xbox",
      "subCategory": "Game Pass",
      "productCategory": "Xbox",
      "productType": "Subscription",
      "platform": "Xbox",
      "tags": "Xbox, Subscription, Microsoft, Digital Code, Game Pass",
      "status": "active",
      "state": "live",
      "type": "Digital",
      "languages": [
        {
          "languageCode": "en",
          "name": "Xbox Game Pass Ultimate 1 month",
          "sku": "GB-EN-4251604177254",
          "image": "https://cdn.kalixo.io/static-images/GB-EN-4251604177254.png",
          "descriptionHtml": "...",
          "description": "...",
          "tncHtml": "...",
          "tnc": "...",
          "redemptionInstructionsHtml": "...",
          "redemptionInstructions": "..."
        }
      ]
    }
  ]
}
```

> **Implementation note:** Paginate through the full catalog by incrementing `skip` by `take` until all `count` products are fetched.

---

### 4. Product Details

**GET** `/catalog/product`

Retrieves a single product by its EAN code.

```bash
curl -X GET \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  "https://api.kalixo.io/v1/catalog/product?ean=4251604177254"
```

**Query Parameters:**

| Parameter | Type   | Required | Description                   |
|-----------|--------|----------|-------------------------------|
| `ean`     | string | Yes      | EAN code of the product       |

**Response:** Same structure as a single product object from the product list response.

---

## Orders

### 5. Place Order

**POST** `/orders/place-order`

Places an order for one or more products. Returns the `orderId` and PINs/codes for each fulfilled product.

```bash
curl -X POST \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "externalOrderCode": "O-12345",
    "price": 4977,
    "currency": "GBP",
    "orderProducts": [
      {
        "productId": 2,
        "price": 3299,
        "sku": "GB-EN-4251604177278",
        "currency": "GBP",
        "quantity": 1
      },
      {
        "productId": 6,
        "price": 839,
        "sku": "GB-EN-4251604133182",
        "currency": "GBP",
        "quantity": 2
      }
    ]
  }' \
  https://api.kalixo.io/v1/orders/place-order
```

**Request Body Fields:**

| Field               | Type    | Required | Description                                      |
|---------------------|---------|----------|--------------------------------------------------|
| `externalOrderCode` | string  | Yes      | Your internal unique order identifier            |
| `price`             | integer | Yes      | Total order price in the smallest currency unit  |
| `currency`          | string  | Yes      | Currency code (e.g. `GBP`)                       |
| `orderProducts`     | array   | Yes      | List of products to order (see below)            |

**`orderProducts` item fields:**

| Field       | Type    | Required | Description                              |
|-------------|---------|----------|------------------------------------------|
| `productId` | integer | Yes      | Kalixo product ID                        |
| `sku`       | string  | Yes      | Language/region-specific SKU             |
| `price`     | integer | Yes      | Unit price in smallest currency unit     |
| `currency`  | string  | Yes      | Currency code                            |
| `quantity`  | integer | Yes      | Number of units to order                 |

**Response:**

```json
{
  "orderId": "59400",
  "products": [
    {
      "price": 3299,
      "buyingPrice": 2999,
      "productId": 2,
      "quantity": 1,
      "pin": [
        { "PIN": "ABCDE-FGHIJ-KLMNO-PQRST-UVWXY" }
      ]
    },
    {
      "price": 839,
      "buyingPrice": 759,
      "productId": 6,
      "quantity": 2,
      "pin": [
        { "PIN": "ABCDE-FGHIJ-KLMNO-PQRST-UVWXY" },
        { "PIN": "ABCDE-FGHIJ-KLMNO-PQRST-UVWXY" }
      ]
    }
  ],
  "wallet": {
    "GBP": { ... }
  }
}
```

> **Notes:**
> - `wallet` is only returned if a balance/wallet is configured for your account.
> - `externalOrderCode` must be unique per order — use your internal order/transaction ID.
> - Store the returned `orderId` for future lookups via the retrieve order endpoint.

---

### 6. Get Order (Retrieve Order)

**GET** `/orders/retrieve-order`

Fetches the details and status of a previously placed order.

```bash
curl -X GET \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  "https://api.kalixo.io/v1/orders/retrieve-order?id=59400"
```

**Query Parameters:**

| Parameter | Type    | Required | Description                   |
|-----------|---------|----------|-------------------------------|
| `id`      | string  | Yes      | Kalixo `orderId` to look up   |

**Response:** Returns the full order object including products, PINs, pricing, and wallet balance (same structure as the place order response).

---

## Seeder

Create a seeder to insert the Gamezcode supplier record into the database, following the same pattern used for Giftery.

```js
// Example seeder structure (adapt to your ORM/framework)
{
  name: 'Gamezcode',
  slug: 'gamezcode',
  baseUrl: 'https://api.kalixo.io/v1',
  credentials: {
    email: process.env.GAMEZCODE_EMAIL,
    password: process.env.GAMEZCODE_PASSWORD,
  },
  isActive: true,
}
```

**Environment variables required:**

```env
GAMEZCODE_EMAIL=your_kalixo_email
GAMEZCODE_PASSWORD=your_kalixo_password
```

---

## Client Implementation Notes

Implement the `GamezcodeClient` (mirroring `GifteryClient`) with the following responsibilities:

1. **Token management** — Login on first use, cache `accessToken` and `refreshToken`, auto-refresh on `401`.
2. **Catalog sync** — Paginate through `/catalog/products` using `skip`/`take` to fetch and upsert all products.
3. **Product detail fetch** — Use `/catalog/product?ean=` for single-product lookups.
4. **Order placement** — Map internal order data to Kalixo's `place-order` payload; persist returned `orderId` and PINs.
5. **Order retrieval** — Use `retrieve-order?id=` to check status or re-fetch PINs if needed.

---

## Reference

- Kalixo Developer Docs: https://developer.kalixo.io
- API Base URL: `https://api.kalixo.io/v1`
