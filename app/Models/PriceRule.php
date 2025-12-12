<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceRule extends Model
{
    /** @use HasFactory<\Database\Factories\PriceRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'match_type',
        'action_mode',
        'action_value',
        'action_operator',
        'status',
    ];

    protected $casts = [
        'action_value' => 'decimal:2',
    ];

    /**
     * Get the conditions for the price rule.
     *
     * @return HasMany<PriceRuleCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(PriceRuleCondition::class);
    }
}
