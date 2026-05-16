<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PetugasRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class PetugasDashboardController extends Controller
{
    use ApiResponse;

    protected $petugasRepo;

    public function __construct(PetugasRepository $petugasRepo)
    {
        $this->petugasRepo = $petugasRepo;
    }

    public function index()
    {
        try {
            // Pastikan hanya petugas yang bisa akses data ini
            if (Auth::user()->role !== 'petugas') {
                return $this->unauthorizedResponse("Hanya untuk petugas lapangan");
            }

            $data = $this->petugasRepo->getDashboardData(Auth::id());
            return $this->successResponse($data, "Data dashboard berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}