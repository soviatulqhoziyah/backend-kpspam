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

    // Endpoint untuk Petugas memproses pembayaran
    public function store(Request $request)
    {
        try {
            $request->validate([
                'billing_ids' => 'required|array',
                'billing_ids.*' => 'exists:billings,id',
                'payment_method' => 'required|in:cash,midtrans,qris,echannel'
            ]);

            if ($request->payment_method === 'cash') {
                $payment = $this->paymentRepo->processCashPayment($request->all());
                return $this->successResponse($payment, "Pembayaran tunai berhasil diproses");
            } else {
                // Petugas yang trigger Midtrans — skip user_id check karena billing milik pelanggan
                $result = $this->paymentRepo->initiateMidtrans($request->all(), true);
                return $this->successResponse($result, "Snap Token pembayaran berhasil didapatkan");
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function syncMidtrans(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $isSynced = $this->paymentRepo->syncMidtrans($request->order_id);
            
            if ($isSynced) {
                return $this->successResponse(null, "Pembayaran berhasil disinkronkan");
            }
            return $this->errorResponse("Transaksi belum selesai atau sudah disinkronkan", 400);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
