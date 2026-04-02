<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleOrder extends Model
{
    /** @use HasFactory<\Database\Factories\SaleOrderFactory> */
    use HasFactory;

    public const MANASTORE = 'manastore';

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

    /**
     * Get all items for this sale order.
     *
     * @return HasMany<SaleOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class);
    }
}
