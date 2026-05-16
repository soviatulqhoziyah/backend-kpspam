<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TarifSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Tarif::create([
            'hargaPerM' => 5000,
            'biayaBeban' => 10000,
            'status' => 'aktif'
        ]);
    }
}
