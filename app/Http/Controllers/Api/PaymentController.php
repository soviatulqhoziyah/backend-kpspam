<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PaymentRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class PaymentController extends Controller
{
    use ApiResponse;

    protected PaymentRepository $paymentRepo;

    public function __construct(PaymentRepository $paymentRepo)
    {
        $this->paymentRepo = $paymentRepo;
    }

    public function checkout(Request $request)
    {
        try {
            $request->validate([
                'billing_ids' => 'required|array',
                'billing_ids.*' => 'exists:billings,id'
            ]);

            $result = $this->paymentRepo->initiateMidtrans($request->all());

            return $this->successResponse($result, "Snap Token berhasil didapatkan");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
