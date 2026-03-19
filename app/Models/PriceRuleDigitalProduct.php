<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceRuleDigitalProduct extends Model
{
    /** @use HasFactory<\Database\Factories\PriceRuleDigitalProductFactory> */
    use HasFactory;

    protected $table = 'price_rule_digital_product';

    protected $fillable = [
        'digital_product_id',
        'price_rule_id',
        'original_selling_price',
        'base_value',
        'action_mode',
        'action_operator',
        'action_value',
        'calculated_price',
        'final_selling_price',
        'applied_at',
    ];

    protected $casts = [
        'original_selling_price' => 'decimal:2',
        'base_value' => 'decimal:2',
        'action_value' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'final_selling_price' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    /**
     * Get the digital product associated with this application.
     *
     * @return BelongsTo<DigitalProduct, $this>
     */
    public function digitalProduct(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class);
    }

    /**
     * Get the price rule associated with this application.
     *
     * @return BelongsTo<PriceRule, $this>
     */
    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }
}
