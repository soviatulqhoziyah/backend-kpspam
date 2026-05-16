<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingRequest;
use App\Repositories\BillingRepository;
use App\Traits\ApiResponse;

class BillingController extends Controller
{
    use ApiResponse;

    protected $billingRepo;

    public function __construct(BillingRepository $billingRepo)
    {
        $this->billingRepo = $billingRepo;
    }

    public function store(BillingRequest $request) 
    {
        try {
            // 1. Ambil data yang sudah lolos validasi
            $validated = $request->validated();

            // 2. Ambil file foto (khusus fitur Smart Metering)
            $file = $request->file('fotoMeteran');

            // 3. Panggil Repository (Kirim data valid + file foto)
            $billing = $this->billingRepo->storeBilling($validated, $file);

            return $this->successResponse($billing, "Tagihan berhasil diterbitkan");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
