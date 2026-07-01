<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Repositories\AuthRepository;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    protected $authRepo;

    public function __construct(AuthRepository $authRepo)
    {
        $this->authRepo = $authRepo;
    }

    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();

            // 1. Cari user di Repository
            $user = $this->authRepo->findByUsername($validated['username']);

            // 2. Cek apakah user ada dan password cocok
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return $this->errorResponse("Username atau password salah", 401);
            }

            // 3. Cek status user
            if ($user->status === 'pending') {
                return $this->errorResponse("Pendaftaran Anda sedang menunggu verifikasi admin.", 403, ['status' => 'pending']);
            }
            if ($user->status === 'ditolak') {
                $pesan = "Pendaftaran Anda ditolak oleh admin.";
                if ($user->catatan_penolakan) $pesan .= " Alasan: " . $user->catatan_penolakan;
                return $this->errorResponse($pesan, 403, ['status' => 'ditolak', 'catatan' => $user->catatan_penolakan]);
            }
            if ($user->status !== 'aktif') {
                return $this->errorResponse("Akun Anda dinonaktifkan. Silakan hubungi admin.", 403);
            }

            // 4. Buat Token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ], "Login Berhasil");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // Tambahkan Request $request di dalam kurung
    public function logout(\Illuminate\Http\Request $request)
    {
        try {
            $user = $request->user();

            // Cek dulu apakah usernya ada dan punya token aktif
            if ($user && $user->currentAccessToken()) {
                // Kita panggil delete() secara aman
                $user->currentAccessToken()->delete();
            }

            return $this->successResponse(null, "Logout Berhasil");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
