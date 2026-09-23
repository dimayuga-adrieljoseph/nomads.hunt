<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ─────────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@nomads.hunt'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // ── Demo customers ────────────────────────────────────────────────────
        $customers = [
            ['name' => 'Pedro Santos',  'email' => 'pedro@demo.com'],
            ['name' => 'Maria Cruz',    'email' => 'maria@demo.com'],
            ['name' => 'John Reyes',    'email' => 'john@demo.com'],
            ['name' => 'Ana Garcia',    'email' => 'ana@demo.com'],
            ['name' => 'Carlos Bautista', 'email' => 'carlos@demo.com'],
            ['name' => 'Sofia Lim',     'email' => 'sofia@demo.com'],
        ];

        foreach ($customers as $customer) {
            User::firstOrCreate(
                ['email' => $customer['email']],
                [
                    'name'     => $customer['name'],
                    'password' => Hash::make('password'),
                    'role'     => 'customer',
                ]
            );
        }
    }
}
