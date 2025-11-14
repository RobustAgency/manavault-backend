<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DigitalProduct extends Model
{
    /** @use HasFactory<\Database\Factories\DigitalProductFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'sku',
        'brand',
        'description',
        'cost_price',
        'status',
        'metadata',
        'source',
        'last_synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cost_price' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
