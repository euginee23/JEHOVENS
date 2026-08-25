<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default administrator account for /admin.
     *
     * Credentials come from config/resort.php, which reads ADMIN_EMAIL and
     * ADMIN_PASSWORD from .env. The defaults are development credentials — override
     * them before seeding anywhere public, or use `php artisan resort:make-admin`.
     */
    public function run(): void
    {
        $email = config('resort.admin.email');
        $password = config('resort.admin.password');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('resort.admin.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Administrator [{$admin->email}] ready — sign in at ".route('login'));

        if ($password === 'password') {
            $this->command->warn('This account uses the default password. Change it before the site is reachable from the internet.');
        }
    }
}
