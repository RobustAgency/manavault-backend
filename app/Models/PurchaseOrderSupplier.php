<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderSupplier extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderSupplierFactory> */
    use HasFactory;

    protected $table = 'purchase_order_suppliers';

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'transaction_id',
        'status',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
