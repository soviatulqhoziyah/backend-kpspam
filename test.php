<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Auth::loginUsingId(2);
$repo = app()->make(App\Repositories\PetugasRepository::class);
echo json_encode($repo->getDashboardData(2));
