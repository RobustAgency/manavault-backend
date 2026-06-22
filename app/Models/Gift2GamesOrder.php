<?php

namespace App\Models;

use App\Enums\Gift2GamesOrderStatus;
use Illuminate\Database\Eloquent\Model;

class Gift2GamesOrder extends Model
{
    protected $table = 'gift2games_orders';

    protected $fillable = [
        'transaction_id',
        'batch_number',
        'status',
    ];

    protected $casts = [
        'status' => Gift2GamesOrderStatus::class,
    ];
}
