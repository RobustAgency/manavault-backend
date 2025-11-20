<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoginLog extends Model
{
    /** @use HasFactory<\Database\Factories\LoginLogFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'activity',
        'logged_in_at',
        'logged_out_at',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];
}
