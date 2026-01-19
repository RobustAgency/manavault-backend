<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;
}
