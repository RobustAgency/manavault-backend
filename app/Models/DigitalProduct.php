<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DigitalProduct extends Model
{
    /** @use HasFactory<\Database\Factories\DigitalProductFactory> */
    use HasFactory;

    public const LOW_QUANTITY_THRESHOLD = 5;

    /**
     * @property string $currency
     */
    protected $fillable = [
        'supplier_id',
        'name',
        'sku',
        'brand',
        'description',
        'tags',
        'region',
        'image_url',
        'cost_price',
        'selling_price',
        'currency',
        'metadata',
        'source',
        'last_synced_at',
        'is_active',
        'in_stock',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'cost_price' => 'decimal:2',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'in_stock' => 'boolean',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
