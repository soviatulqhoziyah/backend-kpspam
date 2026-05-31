<?php
// Generate a token for Petugas 2
$user = \App\Models\User::find(2);
$token = $user->createToken('auth_token')->plainTextToken;
echo $token;
