<?php

namespace App\Models;

use App\Events\NewVouchersAvailable;
use App\Enums\PurchaseOrderItemStatus;
use Illuminate\Database\Eloquent\Model;
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

    protected static function booted(): void
    {
        static::updated(function (self $item): void {
            if (
                $item->wasChanged('status')
                && $item->status === PurchaseOrderItemStatus::FULFILLED
                && $item->digital_product_id !== null
            ) {
                $saleOrderId = $item->purchaseOrder->sale_order_id ?? null;

                event(new NewVouchersAvailable(
                    digitalProductIds: [$item->digital_product_id],
                    purchaseOrderId: $item->purchase_order_id,
                    saleOrderId: $saleOrderId,
                ));
            }
        });
    }

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
