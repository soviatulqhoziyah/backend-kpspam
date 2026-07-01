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

        \App\Models\User::create([
            'username' => 'warga1',
            'password' => bcrypt('password'),
            'role' => 'pelanggan',
            'namaLengkap' => 'Bapak Ahmad',
            'noTelepon' => '08123456712',
            'alamat' => 'talbar',
            'no_kk' => '1234567890123456',
            'status' => 'aktif',
        ]);

        \App\Models\User::create([
            'username' => 'warga_pending',
            'password' => bcrypt('password'),
            'role' => 'pelanggan',
            'namaLengkap' => 'Siti Pendaftar',
            'noTelepon' => '08199998888',
            'alamat' => 'taltim',
            'no_kk' => '6543210987654321',
            'status' => 'pending',
        ]);
    }
}
