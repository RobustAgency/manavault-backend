<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceRuleCondition extends Model
{
    /** @use HasFactory<\Database\Factories\PriceRuleConditionFactory> */
    use HasFactory;

    protected $fillable = [
        'price_rule_id',
        'field',
        'operator',
        'value',
    ];

    /**
     * Get the price rule that owns this condition.
     *
     * @return BelongsTo<PriceRule, $this>
     */
    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }
}
