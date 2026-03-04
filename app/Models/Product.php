<?php

namespace App\Models;

use App\Enums\PriceRule\Status;
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
        'selling_price',
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
        'selling_price' => 'decimal:2',
        'is_out_of_stock' => 'boolean',
    ];

    /**
     * @return BelongsToMany<DigitalProduct, $this, ProductSupplier>
     */
    public function digitalProducts(): BelongsToMany
    {
        return $this->belongsToMany(DigitalProduct::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot('priority');
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
     * Get the selling price of the product, considering any active price rules.
     *
     * @param  float  $value  The original selling price from the database.
     * @return float The final selling price after applying active price rules.
     */
    public function getSellingPriceAttribute($value): float
    {
        $latestPriceRuleProduct = $this->priceRuleProducts()
            ->whereHas('priceRule', function ($query) {
                $query->where('status', Status::ACTIVE->value);
            })
            ->latest('updated_at')
            ->first();

        if ($latestPriceRuleProduct && $latestPriceRuleProduct->final_selling_price) {
            return (float) $latestPriceRuleProduct->final_selling_price;
        }

        return (float) $value;
    }
}
