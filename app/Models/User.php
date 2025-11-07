<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Events\UserCreated;
use App\Events\UserApproved;
use Laravel\Cashier\Billable;
use App\Events\UserApprovalRevoked;
use Illuminate\Auth\Events\Registered;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'is_approved',
        'supabase_id',
        'role',
        'password',
        'plan_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $dispatchesEvents = [
        'created' => UserCreated::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_approved' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Create a new user from Supabase data.
     *
     * @param  array  $attributes  User attributes including name, email, supabase_id, etc.
     * @return self The created user instance
     */
    public static function createFromSupabase(array $attributes): self
    {
        $user = self::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'supabase_id' => $attributes['supabase_id'],
            'is_approved' => true,
            'role' => UserRole::tryFrom(strtolower($attributes['role'] ?? '')) ?? UserRole::USER,
        ]);

        // Dispatch Registered event
        event(new Registered($user));

        return $user;
    }

    /**
     * Approve the user account.
     */
    public function approve(): self
    {
        $this->is_approved = true;
        $this->save();

        event(new UserApproved($this));

        return $this;
    }

    /**
     * Revoke approval for the user account.
     *
     * @return $this
     */
    public function revokeApproval(): self
    {
        event(new UserApprovalRevoked($this));

        $this->delete();

        return $this;
    }
}
