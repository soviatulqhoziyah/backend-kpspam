<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Repositories\PaymentRepository;
use App\Traits\ApiResponse;
use Exception;

class PaymentController extends Controller {
    use ApiResponse;

    protected PaymentRepository $paymentRepo;

    public function __construct(PaymentRepository $paymentRepo) {
        $this->paymentRepo = $paymentRepo;
    }

    public function store(PaymentRequest $request) {
        try {
            $validated = $request->validated();
            $payment = $this->paymentRepo->processPayment($validated);
            
            return $this->successResponse($payment, "Pembayaran berhasil diproses. Tagihan lunas.");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}