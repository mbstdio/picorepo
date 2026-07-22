<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = $this->command->ask('Email', 'admin@example.com');
        $name     = $this->command->ask('Name', 'Admin');
        $password = $this->command->secret('Password');

        if (! $password) {
            $this->command->error('Password cannot be empty.');
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("User {$user->email} created/updated successfully.");
    }
}
