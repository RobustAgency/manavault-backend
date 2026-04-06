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

    /**
     * Get the effective selling price with the following priority:
     * 1. Selling discount (user-set) — always takes precedence
     * 2. Latest price rule automation — applied if no discount exists
     * 3. Base selling_price column — final fallback
     */
    public function getSellingPriceAttribute(): float
    {
        $basePrice = (float) ($this->attributes['face_value'] ?? 0);
        $discount = (float) ($this->attributes['selling_discount'] ?? 0);

        if ($discount > 0) {
            return round($basePrice * (1 - $discount / 100), 2);
        }

        $latestPriceRule = $this->latestPriceRuleDigitalProduct;
        if ($latestPriceRule !== null) {
            return (float) $latestPriceRule->final_selling_price;
        }

        return (float) ($this->attributes['selling_price'] ?? 0);
    }

    /**
     * Get the effective selling discount with the following priority:
     * 1. Stored selling_discount (user-set) — always takes precedence
     * 2. Calculated from face_value and effective selling_price — fallback
     */
    public function getSellingDiscountAttribute(): float
    {
        $storedDiscount = (float) ($this->attributes['selling_discount'] ?? 0);

        if ($storedDiscount > 0) {
            return $storedDiscount;
        }

        $faceValue = (float) ($this->attributes['face_value'] ?? 0);
        $sellingPrice = $this->getSellingPriceAttribute();

        return $faceValue > 0
            ? round((($faceValue - $sellingPrice) / $faceValue) * 100, 2)
            : 0;
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
        $costPrice = (float) $this->getAttribute('cost_price');
        $sellingPrice = $this->getSellingPriceAttribute();

        return round($sellingPrice - $costPrice, 2);
    }
}
