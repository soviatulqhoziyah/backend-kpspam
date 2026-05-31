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

            // 4. Ambil SEMUA tagihan yang masih menunggak untuk user tersebut
            $unpaidBillings = \App\Models\Billing::where('user_id', $validated['user_id'])
                                              ->where('status', 'menunggak')
                                              ->orderBy('periode', 'asc') // Urutkan dari tagihan terlama
                                              ->get();

            return $this->createdResponse([
                'new_billing' => $billing,
                'unpaid_billings' => $unpaidBillings
            ], "Tagihan berhasil diterbitkan");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
