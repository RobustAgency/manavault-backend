<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleOrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\SaleOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
        'conversion_fee',
        'discount_amount',
        'currency',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'conversion_fee' => 'integer',
        'discount_amount' => 'integer',
    ];

    /**
     * Get the sale order this item belongs to.
     *
     * @return BelongsTo<SaleOrder, $this>
     */
    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class);
    }

    /**
     * Get the product for this item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all digital products deducted for this item.
     *
     * @return HasMany<SaleOrderItemDigitalProduct, $this>
     */
    public function digitalProducts(): HasMany
    {
        return $this->hasMany(SaleOrderItemDigitalProduct::class);
    }
}
