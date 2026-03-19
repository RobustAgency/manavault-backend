<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'face_value',
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
        'face_value' => 'decimal:2',
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

    /**
     * Get the price rule applications for this digital product.
     *
     * @return HasMany<PriceRuleDigitalProduct, $this>
     */
    public function priceRuleDigitalProducts(): HasMany
    {
        return $this->hasMany(PriceRuleDigitalProduct::class);
    }

    /**
     * Get the latest price rule application for this digital product.
     *
     * @return HasOne<PriceRuleDigitalProduct, $this>
     */
    public function latestPriceRuleDigitalProduct(): HasOne
    {
        return $this->hasOne(PriceRuleDigitalProduct::class)->latestOfMany('applied_at');
    }

    /**
     * Get the selling price from the latest price rule application,
     * falling back to the stored selling_price if no price rule has been applied.
     */
    public function getSellingPriceAttribute(): float
    {
        $latestPriceRule = $this->latestPriceRuleDigitalProduct;

        if ($latestPriceRule !== null) {
            return (float) $latestPriceRule->final_selling_price;
        }

        return (float) ($this->attributes['selling_price'] ?? 0);
    }
}
