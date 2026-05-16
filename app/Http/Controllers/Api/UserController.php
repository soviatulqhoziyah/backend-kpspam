<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Http\Requests\UserRequest;
use App\Traits\ApiResponse;
use Exception;

class UserController extends Controller
{
    use ApiResponse;

    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function index()
    {
        try {
            $users = $this->userRepo->getAllUsers();
            return $this->successResponse($users, "Daftar user berhasil diambil");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            $validated = $request->validated();
            $user = $this->userRepo->storeUser($validated);
            return $this->successResponse($user, "User berhasil dibuat", 200);
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

    
}
