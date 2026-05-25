<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\AdminRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    protected $adminRepo;

    public function __construct(AdminRepository $adminRepo)
    {
        $this->adminRepo = $adminRepo;
    }

    public function index(Request $request)
    {
        try {
            // Cek role admin
            if (Auth::user()->role !== 'admin') {
                return $this->unauthorizedResponse("Akses khusus Administrator.");
            }

            // Ambil tahun dari request, default ke tahun sekarang
            $year = $request->query('year', date('Y'));

            $data = $this->adminRepo->getYearlySummary($year);

            return $this->successResponse($data, "Data dashboard admin berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function monthlyDetail(Request $request)
    {
        try {
            $month = $request->query('month', date('m'));
            $year = $request->query('year', date('Y'));

            $data = $this->adminRepo->getMonthlyDetail($month, $year);
            return $this->successResponse($data, "Detail transaksi bulanan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function confirmPayment($petugasId)
    {
        try {
            $this->adminRepo->confirmSetoran($petugasId);
            return $this->successResponse(null, "Setoran petugas berhasil dikonfirmasi");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function complaintIndex(Request $request)
    {
        try {
            $data = $this->adminRepo->getComplaintManagement($request);
            return $this->successResponse($data, "Daftar pengaduan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function expenseAudit(Request $request)
    {
        try {
            $data = $this->adminRepo->getExpenseAudit($request);
            return $this->successResponse($data, "Data audit pengeluaran berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function updateExpenseStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approve,reject,pending'
            ]);

            // Gunakan ExpenseRepository yang sudah kita buat sebelumnya untuk update status
            $expense = \App\Models\Expense::findOrFail($id);
            $expense->update(['status' => $request->status]);

            return $this->successResponse($expense, "Status pengeluaran berhasil diperbarui menjadi " . $request->status);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
