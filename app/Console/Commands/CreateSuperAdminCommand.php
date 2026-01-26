<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Enums\UserRole;
use App\Clients\SupabaseClient;
use Illuminate\Console\Command;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
        {email : The email of the admin}
        {--name= : The name of the admin}
        {--password= : The password for the admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user';

    /**
     * Execute the console command.
     */
    public function handle(SupabaseClient $supabase): int
    {
        $this->info('Creating super admin user...');

        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error('An admin with this email already exists.');

            return Command::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Enter admin name');
        $password = $this->option('password') ?? $this->secret('Enter admin password');

        $supabaseUser = $supabase->createUser([
            'email' => $email,
            'password' => $password,
            'name' => $name,
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $supabaseId = $supabaseUser['id'] ?? null;

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => \bcrypt($password),
            'role' => UserRole::SUPER_ADMIN->value,
            'supabase_id' => $supabaseId,
            'is_approved' => true,
        ]);

        $this->info("Super admin '$email' created successfully.");

        return Command::SUCCESS;
    }
}
