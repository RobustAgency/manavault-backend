<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    /** @use HasFactory<\Database\Factories\ModuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the permissions for the module.
     *
     * @return HasMany<Permission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
