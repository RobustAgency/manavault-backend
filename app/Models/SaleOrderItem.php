<?php

namespace App\Models;

use App\Enums\VoucherFulfillmentStatus;
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
        'digital_product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
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
     * Get the digital product (supplier) selected for this item at order creation time.
     * Fulfillment relies on this persisted choice rather than re-resolving the mutable
     *
     * @return BelongsTo<DigitalProduct, $this>
     */
    public function digitalProduct(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
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

    /**
     * Number of vouchers allocated to this item so far.
     */
    public function allocatedVoucherCount(): int
    {
        return $this->digitalProducts->whereNotNull('voucher_id')->count();
    }

    /**
     * Whether every ordered unit of this item has a voucher allocated.
     */
    public function isFullyFulfilled(): bool
    {
        return $this->allocatedVoucherCount() >= $this->quantity;
    }

    /**
     * Voucher fulfillment status for this item: completed once vouchers cover the
     * full ordered quantity, otherwise pending.
     */
    public function voucherFulfillmentStatus(): VoucherFulfillmentStatus
    {
        return $this->isFullyFulfilled()
            ? VoucherFulfillmentStatus::COMPLETED
            : VoucherFulfillmentStatus::PENDING;
    }
}
