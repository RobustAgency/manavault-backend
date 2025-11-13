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
        'brand',
        'description',
        'tags',
        'image',
        'cost_price',
        'status',
        'regions',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'regions' => 'array',
        'metadata' => 'array',
        'cost_price' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
