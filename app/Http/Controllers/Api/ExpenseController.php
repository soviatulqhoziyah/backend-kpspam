<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Repositories\ExpenseRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    use ApiResponse;

    protected ExpenseRepository $expenseRepo;

    public function __construct(ExpenseRepository $expenseRepo)
    {
        $this->expenseRepo = $expenseRepo;
    }

    public function index()
    {
        try {
            $data = $this->expenseRepo->getPetugasSummary(Auth::id());
            return $this->successResponse($data, "Data pengeluaran berhasil diambil");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(ExpenseRequest $request)
    {
        try {
            // 1. Cek apakah user yang login memiliki role 'petugas'
            if (Auth::user()->role !== 'petugas') {
                return $this->unauthorizedResponse("Akses Ditolak. Hanya petugas lapangan yang boleh mencatat pengeluaran.");
            }

            // 2. Jika lolos (dia petugas), baru jalankan logika simpan
            $validated = $request->validated();
            $file = $request->file('fotoBukti');

            $expense = $this->expenseRepo->store($validated, $file);

            return $this->createdResponse($expense, "Pengeluaran berhasil dicatat, menunggu persetujuan admin");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate(['status' => 'required|in:approve,reject,pending']);
            $expense = $this->expenseRepo->updateStatus($id, $request->status);
            return $this->successResponse($expense, "Status pengeluaran diperbarui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
