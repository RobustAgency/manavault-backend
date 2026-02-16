<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PurchaseOrder extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'total_price',
        'order_number',
        'status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    /**
     * @return HasMany<PurchaseOrderSupplier, $this>
     */
    public function purchaseOrderSuppliers(): HasMany
    {
        return $this->hasMany(PurchaseOrderSupplier::class);
    }

    /**
     * Get all suppliers for this purchase order
     *
     * @return HasManyThrough<Supplier, PurchaseOrderSupplier, $this>
     */
    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Supplier::class,
            PurchaseOrderSupplier::class,
            'purchase_order_id',
            'id',
            'id',
            'supplier_id'
        );
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Get the total quantity of all items in this purchase order.
     */
    public function totalQuantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function totalQuantityByInternalSupplier(): int
    {
        return (int) $this->items()
            ->whereHas('digitalProduct.supplier', function ($query) {
                $query->where('type', 'internal');
            })
            ->sum('quantity');
    }
}
