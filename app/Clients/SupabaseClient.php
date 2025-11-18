<?php

namespace App\Clients;

use stdClass;
use Exception;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Enums\UserRole;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class SupabaseClient
{
    /**
     * Supabase API URL.
     */
    private string $apiUrl;

    /**
     * Supabase service key with admin privileges.
     */
    private string $serviceKey;

    /**
     * Supabase JWT secret for token validation.
     */
    private string $jwtSecret;

    /**
     * JWT algorithm.
     */
    private string $algorithm = 'HS256';

    /**
     * Auth api url version.
     */
    private string $authApiVersion = 'v1';

    /**
     * Create a new Supabase client instance.
     */
    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.supabase.url'), '/');
        $this->serviceKey = config('services.supabase.key');
        $this->jwtSecret = config('services.supabase.jwt_secret');
    }

    /**
     * Get the base auth API URL.
     */
    protected function getAuthApiUrl(): string
    {
        return "{$this->apiUrl}/auth/{$this->authApiVersion}";
    }

    /**
     * Get a configured HTTP client for Supabase API communication.
     */
    protected function getClient(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->serviceKey,
            'Authorization' => 'Bearer '.$this->serviceKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'ManaStore/1.0',
        ])
            ->retry(3, 1000, function (Exception $exception, PendingRequest $request) {
                if ($exception instanceof RequestException) {
                    $statusCode = $exception->response->status();
                    // Don't retry on client errors except rate limiting
                    if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                        return false;
                    }
                }

                return true;
            })
            ->baseUrl($this->getAuthApiUrl());
    }

    public function login(string $email, string $password): array
    {
        return $this->getClient()->post('/token?grant_type=password', [
            'email' => $email,
            'password' => $password,
        ])->json();
    }

    /**
     * Create a user in Supabase Auth
     *
     * @param  array  $userData  User data including email, password, and metadata
     * @return array|null The created user data or null on failure
     *
     * @throws Exception If there's an error creating the user
     */
    public function createUser(array $userData): ?array
    {
        $response = $this->getClient()->post('/admin/users', [
            'email' => $userData['email'],
            'password' => $userData['password'] ?? Str::random(12),
            'email_confirm' => $userData['email_verified'] ?? true,
            'user_metadata' => [
                'name' => $userData['name'],
            ],
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Failed to create user in Supabase: '.$response->body());
    }

    /**
     * Update user data in Supabase
     *
     * @param  string  $userId  The Supabase user ID
     * @param  array  $userData  The user data to update
     * @return bool Whether the update was successful
     *
     * @throws Exception If there's an error updating the user
     */
    public function updateUser(string $userId, array $userData): bool
    {
        $response = $this->getClient()->put("/admin/users/{$userId}", $userData);

        if (! $response->successful()) {
            throw new Exception('Failed to update user in Supabase: '.$response->body());
        }

        return true;
    }

    /**
     * Delete a user from Supabase
     *
     * @param  string  $userId  The Supabase user ID
     * @return bool Whether the deletion was successful
     *
     * @throws Exception If there's an error deleting the user
     */
    public function deleteUser(string $userId): bool
    {
        $response = $this->getClient()->delete("/admin/users/{$userId}");

        if (! $response->successful()) {
            throw new Exception('Failed to delete user in Supabase: '.$response->body());
        }

        return true;
    }

    /**
     * Generate a temporary password for a user
     *
     * @return string A randomly generated password
     */
    public function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }

    /**
     * Send a password reset email
     *
     * @param  string  $email  The email address to send the reset to
     * @return bool Whether the email was sent successfully
     *
     * @throws Exception If there's an error sending the password reset email
     */
    public function sendPasswordResetEmail(string $email): bool
    {
        $response = $this->getClient()->post('/recover', [
            'email' => $email,
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to send password reset email: '.$response->body());
        }

        return true;
    }

    /**
     * Get a user by their ID
     *
     * @param  string  $userId  The Supabase user ID
     * @return array|null The user data or null if not found
     *
     * @throws Exception If there's an error getting the user
     */
    public function getUser(string $userId): ?array
    {
        $response = $this->getClient()->get("/admin/users/{$userId}");

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 404) {
            return null;
        }

        throw new Exception('Failed to get user from Supabase: '.$response->body());
    }

    /**
     * List users with pagination
     *
     * @param  int  $page  The page number
     * @param  int  $perPage  The number of users per page
     * @return array The list of users
     *
     * @throws Exception If there's an error listing users
     */
    public function listUsers(int $page = 1, int $perPage = 50): array
    {
        $response = $this->getClient()->get('/admin/users', [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('Failed to list users from Supabase: '.$response->body());
    }

    /**
     * Validate a JWT token from Supabase and extract user data
     *
     * @param  string  $token  The JWT token to validate
     * @return array|null The user data or null if the token is invalid
     */
    public function validateToken(string $token): ?array
    {
        try {
            // Verify and decode the token
            $payload = JWT::decode($token, new Key($this->jwtSecret, $this->algorithm));
            // Check if token is expired
            $now = time();
            if (isset($payload->exp) && $payload->exp < $now) {

                return null;
            }

            // Verify the token was issued by Supabase
            if (! isset($payload->iss) || $payload->iss !== $this->getAuthApiUrl()) {

                return null;
            }

            // Extract user data from payload
            return $this->extractUserDataFromPayload($payload);
        } catch (Exception $e) {
            Log::warning('Supabase JWT validation error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Extract user data from JWT payload
     *
     * @param  stdClass  $payload  The JWT payload
     * @return array The extracted user data
     */
    protected function extractUserDataFromPayload(stdClass $payload): array
    {
        // These values are expected to be present in the Supabase JWT
        $userData = [
            'supabase_id' => $payload->sub,
        ];
        // Extract user metadata if available
        if (isset($payload->user_metadata)) {
            $userData['name'] = $payload->user_metadata->full_name ?? '';
            $userData['email'] = $payload->user_metadata->email ?? $payload->email;
            $userData['email_verified'] = $payload->user_metadata->email_verified ?? false;
            $userData['role'] = $payload->user_metadata->role ?? UserRole::USER;
        }

        return $userData;
    }

    /**
     * Sync a user from Supabase to the local database
     *
     * @param  array  $userData  The user data from Supabase
     * @return User|null The synced user or null on failure
     *
     * @throws Exception If there's an error syncing the user
     */
    public function syncUser(array $userData): ?User
    {
        Log::info('Syncing user from Supabase', ['supabase' => $userData]);
        // Try to find the user by Supabase ID
        // FIXME: This needs to have test, case is we might have a user but supabase does not have that user or deleted from there.
        $user = User::where('supabase_id', $userData['supabase_id'])->orWhere('email', $userData['email'])->first();

        if (! $user) {
            // Create new user if they don't exist using the factory method
            $user = User::createFromSupabase([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'supabase_id' => $userData['supabase_id'],
                'role' => $userData['role'],
            ]);

            Log::info('Created new user from Supabase', ['user_id' => $user->id]);
        } else {
            // Update existing user if needed
            $needsUpdate = false;
            $updates = [];

            if ($user->supabase_id !== $userData['supabase_id']) {
                $updates['supabase_id'] = $userData['supabase_id'];
                $needsUpdate = true;
            }

            if ($user->name !== $userData['name']) {
                $updates['name'] = $userData['name'];
                $needsUpdate = true;
            }

            if ($user->email !== $userData['email']) {
                $updates['email'] = $userData['email'];
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $user->update($updates);
                Log::info('Updated user from Supabase', [
                    'user_id' => $user->id,
                    'fields' => array_keys($updates),
                ]);
            }
        }

        return $user;
    }
}
