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
        'selling_discount',
        'selling_discount_updated_at',
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
        'selling_discount' => 'decimal:2',
        'selling_discount_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'in_stock' => 'boolean',
    ];

    protected $appends = [
        'cost_price_discount',
        'profit_margin',
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

    public function getCostPriceDiscountAttribute(): float
    {
        $faceValue = $this->getAttribute('face_value');
        $costPrice = $this->getAttribute('cost_price');

        return $faceValue > 0
            ? round((($faceValue - $costPrice) / $faceValue) * 100, 2)
            : 0;
    }

    public function getProfitMarginAttribute(): float
    {
        $costPrice = $this->getAttribute('cost_price');
        $sellingPrice = $this->getSellingPriceAttribute();

        return round($sellingPrice - $costPrice, 2);
    }

    /**
     * Get the effective selling price.
     */
    public function getSellingPriceAttribute(): float
    {
        $basePrice = (float) ($this->attributes['face_value'] ?? 0);
        $discount = (float) ($this->attributes['selling_discount'] ?? 0);
        $latestPriceRule = $this->latestPriceRuleDigitalProduct;

        switch ($this->resolvePricingSource()) {
            case 'discount':
                return round($basePrice * (1 - $discount / 100), 2);

            case 'rule':
                return (float) $latestPriceRule->final_selling_price;

            default:
                return (float) ($this->attributes['selling_price'] ?? 0);
        }
    }

    /**
     * Get the effective selling discount.
     */
    public function getSellingDiscountAttribute(): float
    {
        $basePrice = (float) ($this->attributes['face_value'] ?? 0);
        $storedDiscount = (float) ($this->attributes['selling_discount'] ?? 0);
        $latestPriceRule = $this->latestPriceRuleDigitalProduct;

        switch ($this->resolvePricingSource()) {
            case 'discount':
                return $storedDiscount;

            case 'rule':
                $price = (float) $latestPriceRule->final_selling_price;

                return $basePrice > 0
                    ? round((($basePrice - $price) / $basePrice) * 100, 2)
                    : 0;

            default:
                $price = (float) ($this->attributes['selling_price'] ?? 0);

                return $basePrice > 0
                    ? round((($basePrice - $price) / $basePrice) * 100, 2)
                    : 0;
        }
    }

    /**
     * Determine the active pricing source.
     */
    private function resolvePricingSource(): string
    {
        $discount = (float) ($this->attributes['selling_discount'] ?? 0);
        $latestPriceRule = $this->latestPriceRuleDigitalProduct;

        $hasDiscount = $discount >= 0;
        $hasPriceRule = $latestPriceRule !== null;

        if ($hasDiscount && $hasPriceRule) {
            $discountUpdatedAt = $this->getAttribute('selling_discount_updated_at');
            $priceRuleAppliedAt = $latestPriceRule->applied_at;

            return ($discountUpdatedAt !== null && $discountUpdatedAt >= $priceRuleAppliedAt)
                ? 'discount'
                : 'rule';
        }

        if ($hasDiscount) {
            return 'discount';
        }
        if ($hasPriceRule) {
            return 'rule';
        }

        return 'fallback';
    }
}
