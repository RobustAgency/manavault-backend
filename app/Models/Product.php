<?php

namespace App\Models;

use App\Enums\Product\Lifecycle;
use App\Enums\Product\FulfillmentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'brand_id',
        'description',
        'short_description',
        'long_description',
        'tags',
        'image',
        'face_value',
        'currency',
        'status',
        'regions',
        'fulfillment_mode',
        'is_out_of_stock',
    ];

    protected $casts = [
        'tags' => 'array',
        'regions' => 'array',
        'face_value' => 'decimal:2',
        'is_out_of_stock' => 'boolean',
    ];

    protected $appends = [
        'selling_price',
    ];

    /**
     * @return BelongsToMany<DigitalProduct, $this, ProductSupplier>
     */
    public function digitalProducts(): BelongsToMany
    {
        return $this->belongsToMany(DigitalProduct::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot('priority')
            ->where('digital_products.is_active', true)
            ->where('digital_products.in_stock', true);
    }

    public function digitalProduct(): ?DigitalProduct
    {
        $query = $this->digitalProducts();

        return $this->fulfillment_mode === FulfillmentMode::MANUAL->value
            ? $query->orderByPivot('priority')->first()
            : $query->orderBy('digital_products.cost_price')->first();
    }

    /**
     * Get the brand that owns the product.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the price rule applications for this product.
     *
     * @return HasMany<PriceRuleProduct, $this>
     */
    public function priceRuleProducts(): HasMany
    {
        return $this->hasMany(PriceRuleProduct::class);
    }

    /**
     * Get the selling price of the product, considering digital product associations and its priority.

     *
     * @return float The final selling price after applying active price rules.
     */
    public function getSellingPriceAttribute(): float
    {
        $digitalProduct = $this->digitalProduct();

        return $digitalProduct ? (float) $digitalProduct->selling_price : 0.0;
    }

    public function getStatusAttribute(?string $value): string
    {
        if ($this->getSellingPriceAttribute() <= 0.0) {
            return Lifecycle::IN_ACTIVE->value;
        }

        return $value;
    }
}
