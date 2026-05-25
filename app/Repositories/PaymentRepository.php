<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Billing;
use Midtrans\Snap;
use Midtrans\Config;
use Exception;
use Illuminate\Support\Facades\Auth;

class PaymentRepository
{
    protected $model;

    public function __construct(Payment $model)
    {
        $this->model = $model;
        // Konfigurasi Midtrans dari .env
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function initiateMidtrans($data)
    {
        $billings = Billing::whereIn('id', $data['billing_ids'])->get();
        $totalAmount = $billings->sum('totalTagihan');
        $orderId = 'INV-' . time() . '-' . Auth::id();

        // SIMPAN order_id ke semua billing yang dipilih
        foreach ($billings as $bill) {
            $bill->update(['midtrans_order_id' => $orderId]);
        }

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $totalAmount,
            ],
            'customer_details' => [
                'first_name' => Auth::user()->namaLengkap,
                'phone' => Auth::user()->noTelepon,
            ],
            'enabled_payments' => ['qris', 'gopay', 'shopeepay', 'other_va']
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return [
            'redirect_url' => "https://app.sandbox.midtrans.com/snap/v2/vtweb/" . $snapToken,
            'snap_token' => $snapToken,
            'order_id' => $orderId,
            'total_bayar' => $totalAmount
        ];
    }
}
