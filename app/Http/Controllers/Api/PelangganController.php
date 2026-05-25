<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PelangganRepository;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Request;

class PelangganController extends Controller
{
    use ApiResponse;

    protected $pelangganRepo;

    public function __construct(PelangganRepository $pelangganRepo)
    {
        $this->pelangganRepo = $pelangganRepo;
    }

    public function index()
    {
        try {
            // Pastikan hanya pelanggan yang bisa akses
            if (Auth::user()->role !== 'pelanggan') {
                return $this->unauthorizedResponse("Endpoint ini khusus untuk pelanggan.");
            }

            $data = $this->pelangganRepo->getUnpaidBillings(Auth::id());
            return $this->successResponse($data, "Data tagihan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function history(Request $request)
    {
        try {
            $data = $this->pelangganRepo->getPaymentHistory(Auth::id(), $request);
            return $this->successResponse($data, "Riwayat pembayaran berhasil dimuat");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function myComplaints()
    {
        try {
            $data = $this->pelangganRepo->getMyComplaints(Auth::id());
            return $this->successResponse($data, "Data pengaduan berhasil dimuat");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function profile()
    {
        try {
            $data = $this->pelangganRepo->getProfileData(Auth::id());
            return $this->successResponse($data, "Data profil pelanggan berhasil dimuat");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
