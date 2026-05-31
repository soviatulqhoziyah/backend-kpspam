<?php
Auth::loginUsingId(2);
$repo = app(\App\Repositories\PetugasRepository::class);
echo json_encode($repo->getDashboardData(2));
