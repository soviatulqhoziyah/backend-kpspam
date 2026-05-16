<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    protected $authService;

    // Masukkan AuthService ke dalam Controller
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        // Validasi input
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Panggil logika login dari AuthService
        $result = $this->authService->login($request->username, $request->password);

        if (!$result) {
            return $this->errorResponse('Username atau password salah', 401);
        }

        return $this->successResponse($result, 'Login Berhasil');
    }

    public function logout(Request $request)
    {
        // Menghapus token yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout Berhasil');
    }
}