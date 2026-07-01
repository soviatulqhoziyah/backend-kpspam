<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Repositories\UserRepository;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function index(Request $request)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->unauthorizedResponse("Hanya admin yang dapat mengelola data user.");
            }

            $data = $this->userRepo->getPaginatedUsers($request);
            return $this->successResponse($data, "Daftar user berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            $validated = $request->validated();
            $user = $this->userRepo->storeUser($validated);
            return $this->createdResponse($user, "User berhasil dibuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(UserRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $user = $this->userRepo->updateUser($id, $validated);
            return $this->successResponse($user, "User berhasil diperbarui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    public function destroy($id)
    {
        try {
            $this->userRepo->deleteUser($id);
            return $this->successResponse(null, "User berhasil dihapus");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getPendingUsers()
    {
        try {
            $data = $this->userRepo->getPendingUsers();
            return $this->successResponse($data, "Daftar verifikasi berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            $this->userRepo->approveUser($id);
            return $this->successResponse(null, "Pendaftaran berhasil disetujui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $request->validate(['catatan' => 'nullable|string|max:500']);
            $this->userRepo->rejectUser($id, $request->catatan);
            return $this->successResponse(null, "Pendaftaran berhasil ditolak");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
