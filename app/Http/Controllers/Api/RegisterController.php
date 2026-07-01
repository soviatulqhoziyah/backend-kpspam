<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SupabaseStorage;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'namaLengkap'    => 'required|string|max:100',
                'username'       => 'required|string|unique:users,username',
                'password'       => 'required|string|min:6',
                'noTelepon'      => 'required|string|max:20',
                'alamat'         => 'required|in:talbar,taltim',
                'no_kk'          => 'required|string|size:16',
                'foto_kk_base64' => 'required|string',
                'foto_kk_ext'    => 'required|string|in:jpg,jpeg,png',
            ]);

            // Cek no_kk: tolak jika sudah aktif atau sedang pending
            $existingKk = User::where('no_kk', $validated['no_kk'])
                ->whereIn('status', ['aktif', 'pending'])
                ->first();
            if ($existingKk) {
                $pesan = $existingKk->status === 'aktif'
                    ? 'No. KK ini sudah terdaftar dan aktif.'
                    : 'No. KK ini sudah memiliki pendaftaran yang sedang menunggu verifikasi.';
                return $this->errorResponse(['no_kk' => [$pesan]], 422);
            }

            $imageData = base64_decode($validated['foto_kk_base64']);
            if ($imageData === false) {
                return $this->errorResponse('Data foto KK tidak valid.', 422);
            }
            $filename = 'foto_kk_' . time() . '_' . preg_replace('/[^a-z0-9]/', '_', strtolower($validated['username'])) . '.' . $validated['foto_kk_ext'];
            $fotoKkUrl = SupabaseStorage::upload('foto_kk/' . $filename, $imageData, $validated['foto_kk_ext']);

            $user = User::create([
                'namaLengkap' => $validated['namaLengkap'],
                'username'    => $validated['username'],
                'password'    => Hash::make($validated['password']),
                'noTelepon'   => $validated['noTelepon'],
                'alamat'      => $validated['alamat'],
                'no_kk'       => $validated['no_kk'],
                'foto_kk'     => $fotoKkUrl,
                'role'        => 'pelanggan',
                'status'      => 'pending',
            ]);

            return $this->createdResponse([
                'id'          => $user->id,
                'namaLengkap' => $user->namaLengkap,
                'username'    => $user->username,
                'status'      => $user->status,
            ], "Pendaftaran berhasil! Akun Anda sedang menunggu verifikasi admin.");
        } catch (ValidationException $e) {
            return $this->errorResponse($e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function checkStatus(Request $request)
    {
        try {
            $request->validate(['username' => 'required|string']);
            $user = User::where('username', $request->username)
                ->where('role', 'pelanggan')
                ->where('status', '!=', 'aktif')
                ->first();

            if (!$user) {
                return $this->errorResponse("Username tidak ditemukan atau akun sudah aktif.", 404);
            }

            return $this->successResponse([
                'status'            => $user->status,
                'catatan_penolakan' => $user->catatan_penolakan,
            ], "Status pendaftaran berhasil dimuat.");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
