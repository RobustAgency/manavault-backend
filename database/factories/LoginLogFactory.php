<?php

namespace Database\Factories;

use App\Models\LoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoginLog>
 */
class LoginLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = LoginLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loggedInAt = fake()->dateTimeBetween('-30 days', 'now');
        $hasLoggedOut = fake()->boolean(70); // 70% chance of being logged out

        return [
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'activity' => fake()->randomElement(['login', 'logout', 'failed_login', 'password_reset', 'session_expired']),
            'logged_in_at' => $loggedInAt,
            'logged_out_at' => $hasLoggedOut ? fake()->dateTimeBetween($loggedInAt, 'now') : null,
        ];
    }

    /**
     * Indicate that the user is still logged in (no logout time).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'logged_out_at' => null,
            'activity' => 'login',
        ]);
    }

    /**
     * Indicate that this is a failed login attempt.
     */
    public function failedLogin(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity' => 'failed_login',
            'logged_out_at' => null,
        ]);
    }

    /**
     * Indicate that this is a successful login with logout.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $loggedInAt = $attributes['logged_in_at'] ?? fake()->dateTimeBetween('-30 days', 'now');

            return [
                'activity' => 'logout',
                'logged_out_at' => fake()->dateTimeBetween($loggedInAt, 'now'),
            ];
        });
    }

    /**
     * Set a specific email for the login log.
     */
    public function forEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    /**
     * Set a specific IP address for the login log.
     */
    public function fromIp(string $ipAddress): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ipAddress,
        ]);
    }
}
