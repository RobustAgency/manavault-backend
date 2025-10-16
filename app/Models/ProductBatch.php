<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ProductBatchFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'supplier_id', 'purchase_price', 'quantity'];
}
