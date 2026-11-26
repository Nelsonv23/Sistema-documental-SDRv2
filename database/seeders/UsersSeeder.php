<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador SDR',
                'email' => 'admin@sdr.local',
                'password' => 'Sdr2026!',
                'role' => 'Administrador',
                'is_active' => true,
            ],
            [
                'name' => 'Jefatura SDR',
                'email' => 'jefatura@sdr.local',
                'password' => 'Sdr2026!',
                'role' => 'Jefatura',
                'is_active' => true,
            ],
            [
                'name' => 'Colaborador SDR',
                'email' => 'colaborador@sdr.local',
                'password' => 'Sdr2026!',
                'role' => 'Colaborador',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'is_active' => $userData['is_active'],
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
