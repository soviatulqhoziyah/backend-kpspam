<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\User::create([
            'username' => 'admin123',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'namaLengkap' => 'Administrator SPAMS',
            'noTelepon' => '08123456789',
            'alamat' => 'talbar',
        ]);

        \App\Models\User::create([
            'username' => 'petugas1',
            'password' => bcrypt('password'),
            'role' => 'petugas',
            'namaLengkap' => 'Budi Petugas',
            'noTelepon' => '08123456711',
            'alamat' => 'taltim',
        ]);
    }
}
