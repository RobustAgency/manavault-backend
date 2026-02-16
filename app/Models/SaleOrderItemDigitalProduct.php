<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleOrderItemDigitalProduct extends Model
{
    /** @use HasFactory<\Database\Factories\SaleOrderItemDigitalProductFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_order_item_id',
        'digital_product_id',
        'voucher_id',
    ];

    /**
     * Get the sale order item this digital product belongs to.
     *
     * @return BelongsTo<SaleOrderItem, $this>
     */
    public function saleOrderItem(): BelongsTo
    {
        return $this->belongsTo(SaleOrderItem::class);
    }

    /**
     * Get the digital product.
     *
     * @return BelongsTo<DigitalProduct, $this>
     */
    public function digitalProduct(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class);
    }

    /**
     * Get the voucher allocated to this digital product in the order.
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
