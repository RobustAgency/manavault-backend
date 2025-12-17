<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductSupplier extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_supplier';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => 'integer',
    ];

    protected $fillable = [
        'product_id',
        'digital_product_id',
        'priority',
    ];
}
