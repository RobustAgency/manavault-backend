<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoucherAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\VoucherAuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
