<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'email',
        'company_name',
        'company_email',
    ];

    /**
     * Get all sale orders placed by this customer.
     *
     * @return HasMany<SaleOrder, $this>
     */
    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }
}
