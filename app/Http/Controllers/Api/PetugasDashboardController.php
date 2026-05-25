<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PetugasRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Http\Request;

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
            if (Auth::user()->role !== 'petugas') {
                return $this->unauthorizedResponse("Hanya untuk petugas lapangan");
            }

            $data = $this->petugasRepo->getDashboardData(Auth::id());
            return $this->successResponse($data, "Data dashboard berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }


    public function getCustomers(Request $request)
    {
        try {
            $data = $this->petugasRepo->getCustomerList($request);
            return $this->successResponse($data, "Daftar pelanggan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function showCustomer($id)
    {
        try {
            $data = $this->petugasRepo->getCustomerDetail($id);
            return $this->successResponse($data, "Detail pelanggan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse("Data pelanggan tidak ditemukan atau sudah tidak aktif.", 404);
        }
    }

    public function profile()
    {
        try {
            $data = $this->petugasRepo->getProfileData(Auth::id());
            return $this->successResponse($data, "Data profil berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
