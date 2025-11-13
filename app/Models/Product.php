<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'brand',
        'description',
        'short_description',
        'long_description',
        'tags',
        'image',
        'selling_price',
        'status',
        'regions',
    ];

    protected $casts = [
        'tags' => 'array',
        'regions' => 'array',
    ];

    /**
     * @return BelongsToMany<DigitalProduct, $this>
     */
    public function digitalProducts(): BelongsToMany
    {
        return $this->belongsToMany(DigitalProduct::class, 'product_supplier');
    }
}
