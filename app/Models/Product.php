<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'out_of_stock',
    ];

    protected $casts = [
        'tags' => 'array',
        'regions' => 'array',
        'face_value' => 'decimal:2',
        'out_of_stock' => 'boolean',
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
}
