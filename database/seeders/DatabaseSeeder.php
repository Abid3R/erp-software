<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Admin credentials come from the environment (spec #59 — never hard-code a
     * production password). Dev defaults are provided only for local use; set
     * ADMIN_EMAIL / ADMIN_PASSWORD in .env for anything real.
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $admin */
        $admin = config('erp.admin');

        User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => Hash::make($admin['password']),
                'email_verified_at' => now(),
            ],
        );

        $this->command->info("Admin user ensured: {$admin['email']}");
    }
}
