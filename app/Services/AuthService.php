<?php

namespace App\Services;

use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected $authRepo;

    // Menghubungkan Service dengan Repository
    public function __construct(AuthRepository $authRepo)
    {
        $this->authRepo = $authRepo;
    }

    public function login($username, $password)
    {
        // 1. Cek user ke Repository
        $user = $this->authRepo->findByUsername($username);

        // 2. Cek apakah user ada dan passwordnya benar
        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        // 3. SANCTUM BEKERJA DI SINI: Membuat Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }
}