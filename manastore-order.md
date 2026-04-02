# Sale Order Pricing Source of Truth

## Overview

For sale orders, the **source of truth for pricing must be Manastore**.

### Reason

Currently:
- Pricing is being calculated on **Manavault**
- However, **Manastore admins can apply discounts directly on products**
- These discounts are **not reflected in Manavault**

### Decision

➡️ All sale order pricing **must come from Manastore** to ensure accuracy.

---

## Wallet-Based Order Handling

Users can place orders using:
- **USD Wallet**
- **EUR Wallet**

Below are the response structures for each case.

---

## USD Wallet Response

```
{
  "order": {
    "id": 25,
    "user_id": 4,
    "order_number": 1024,
    "fulfillment_status": "processing",
    "payment_status": "completed",
    "order_type": "paid",
    "subtotal": {
      "amount": "8368",
      "currency": "USD"
    },
    "conversion_fees": {
      "amount": "27",
      "currency": "USD"
    },
    "total": {
      "amount": "8395",
      "currency": "USD"
    },
    "currency": "USD",
    "wallet_id": null,
    "transaction_id": "01kn6csx05wzy94gw5j17hry4b",
    "meta": null,
    "created_at": "2026-04-02T06:06:22.000000Z",
    "updated_at": "2026-04-02T06:06:33.000000Z",
    "deleted_at": null,
    "items": [
      {
        "id": 29,
        "order_id": 25,
        "product_variant_id": 202,
        "variant_name": "PlayStation Network Card G2g",
        "product_name": "PlayStation Network Card G2g",
        "purchase_price": {
          "amount": "4508",
          "currency": "USD"
        },
        "price": {
          "amount": "2668",
          "currency": "USD"
        },
        "currency": "USD",
        "quantity": 2,
        "stock_id": 124,
        "conversion_fee": {
          "amount": "27",
          "currency": "USD"
        },
        "total_price": {
          "amount": "2668",
          "currency": "USD"
        },
        "discount_amount": {
          "amount": "2668",
          "currency": "USD"
        },
        "status": "pending",
        "created_at": "2026-04-02T06:06:33.000000Z",
        "updated_at": "2026-04-02T06:06:33.000000Z"
      },
      {
        "id": 30,
        "order_id": 25,
        "product_variant_id": 206,
        "variant_name": "Prod SHJ",
        "product_name": "Prod SHJ",
        "purchase_price": {
          "amount": "2000",
          "currency": "USD"
        },
        "price": {
          "amount": "3000",
          "currency": "USD"
        },
        "currency": "USD",
        "quantity": 2,
        "stock_id": 129,
        "conversion_fee": {
          "amount": "0",
          "currency": "USD"
        },
        "total_price": {
          "amount": "5700",
          "currency": "USD"
        },
        "discount_amount": {
          "amount": "300",
          "currency": "USD"
        },
        "status": "pending",
        "created_at": "2026-04-02T06:06:33.000000Z",
        "updated_at": "2026-04-02T06:06:33.000000Z"
      }
    ]
  }
} 
```

## USD Wallet Response

```
{
  "order": {
    "id": 26,
    "user_id": 4,
    "order_number": 1025,
    "fulfillment_status": "processing",
    "payment_status": "completed",
    "order_type": "paid",
    "subtotal": {
      "amount": "7214",
      "currency": "EUR"
    },
    "conversion_fees": {
      "amount": "49",
      "currency": "EUR"
    },
    "total": {
      "amount": "7263",
      "currency": "EUR"
    },
    "currency": "EUR",
    "wallet_id": null,
    "transaction_id": "01kn6d37yx4ktvgptknyt21pv1",
    "meta": null,
    "created_at": "2026-04-02T06:11:29.000000Z",
    "updated_at": "2026-04-02T06:11:39.000000Z",
    "deleted_at": null,
    "items": [
      {
        "id": 31,
        "order_id": 26,
        "product_variant_id": 202,
        "variant_name": "PlayStation Network Card G2g",
        "product_name": "PlayStation Network Card G2g",
        "purchase_price": {
          "amount": "3886",
          "currency": "EUR"
        },
        "price": {
          "amount": "2300",
          "currency": "EUR"
        },
        "currency": "EUR",
        "quantity": 2,
        "stock_id": 124,
        "conversion_fee": {
          "amount": "0",
          "currency": "EUR"
        },
        "total_price": {
          "amount": "2300",
          "currency": "EUR"
        },
        "discount_amount": {
          "amount": "2300",
          "currency": "EUR"
        },
        "status": "pending",
        "created_at": "2026-04-02T06:11:29.000000Z",
        "updated_at": "2026-04-02T06:11:29.000000Z"
      },
      {
        "id": 32,
        "order_id": 26,
        "product_variant_id": 206,
        "variant_name": "Prod SHJ",
        "product_name": "Prod SHJ",
        "purchase_price": {
          "amount": "1724",
          "currency": "EUR"
        },
        "price": {
          "amount": "2586",
          "currency": "EUR"
        },
        "currency": "EUR",
        "quantity": 2,
        "stock_id": 129,
        "conversion_fee": {
          "amount": "49",
          "currency": "EUR"
        },
        "total_price": {
          "amount": "4914",
          "currency": "EUR"
        },
        "discount_amount": {
          "amount": "258",
          "currency": "EUR"
        },
        "status": "pending",
        "created_at": "2026-04-02T06:11:39.000000Z",
        "updated_at": "2026-04-02T06:11:39.000000Z"
      }
    ]
  }
}
```