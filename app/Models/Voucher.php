<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    /** @use HasFactory<\Database\Factories\VoucherFactory> */
    use HasFactory;

    public const SUPPORTED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'zip'];

    protected $fillable = [
        'code',
        'purchase_order_id',
        'serial_number',
        'status',
        'pin_code',
        'stock_id',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
