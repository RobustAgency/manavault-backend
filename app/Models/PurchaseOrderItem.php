<?php

namespace App\Models;

use App\Enums\PurchaseOrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use App\Events\PurchaseOrderItemUpdated;
use App\Contracts\SupplierIntegrationContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Supplier\SupplierIntegrationResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'digital_product_id',
        'digital_product_name',
        'digital_product_sku',
        'digital_product_brand',
        'quantity',
        'unit_cost',
        'subtotal',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'status' => PurchaseOrderItemStatus::class,
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected $dispatchesEvents = [
        // Both the PurchaseOrder (via PurchaseOrderCompleted) and the PurchaseOrderItem
        // (via PurchaseOrderItemUpdated) ultimately dispatch the same NewVouchersAvailable
        // event. We currently rely on the PurchaseOrder-completed path, so this per-item
        // dispatch is left commented out (not removed) on purpose: the plan is to switch
        // to this item-level event in the future instead of waiting for the whole
        // PurchaseOrder to be marked completed.
        // 'updated' => PurchaseOrderItemUpdated::class,
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<DigitalProduct, $this>
     */
    public function digitalProduct(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class);
    }

    public function getSupplier(): ?SupplierIntegrationContract
    {
        $supplier = Supplier::find($this->supplier_id);

        return app(SupplierIntegrationResolver::class)->resolve($supplier);
    }
}
