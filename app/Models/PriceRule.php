<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceRule extends Model
{
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
