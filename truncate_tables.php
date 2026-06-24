<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::table('payments')->truncate();
DB::table('billings')->truncate();
DB::table('complaints')->truncate();
DB::table('expenses')->truncate();
DB::table('tarifs')->truncate();
DB::table('cache')->truncate();
DB::table('jobs')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Semua tabel berhasil dikosongkan.\n";
