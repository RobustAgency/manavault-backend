<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Clients\SupabaseClient;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class SupabaseGuard implements Guard
{
    use GuardHelpers;

    /**
     * The request instance.
     */
    protected Request $request;

    /**
     * The name of the guard.
     */
    protected string $name;

    /**
     * The Supabase client.
     */
    protected SupabaseClient $supabaseClient;

    /**
     * Create a new authentication guard.
     */
    public function __construct(string $name, UserProvider $provider, Request $request, SupabaseClient $supabaseClient)
    {
        $this->name = $name;
        $this->request = $request;
        $this->provider = $provider;
        $this->supabaseClient = $supabaseClient;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user()
    {
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (! is_null($this->user)) {
            return $this->user;
        }

        // Check if the user is already authenticated via web guard
        // This provides compatibility with tests that use actingAs() without guard
        if ($user = Auth::guard('web')->user()) {
            $this->user = $user;

            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (! $token) {
            return null;
        }

        // Validate token and get user data in one step
        $userData = $this->supabaseClient->validateToken($token);
        if (! $userData) {
            return null;
        }

        // Sync the user with our database
        $this->user = $this->supabaseClient->syncUser($userData);

        return $this->user;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        return false; // We don't use this method as login is handled by Supabase directly
    }

    /**
     * Get the token from the request.
     */
    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Set the current user.
     *
     * @return $this
     */
    public function setUser(?Authenticatable $user)
    {
        $this->user = $user;

        return $this;
    }
}
