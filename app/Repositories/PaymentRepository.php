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

    public function initiateMidtrans($data, bool $skipUserCheck = false)
    {
        $query = Billing::whereIn('id', $data['billing_ids'])
            ->where('status', '!=', 'lunas');

        if (!$skipUserCheck) {
            $query->where('user_id', Auth::id());
        }

        $billings = $query->get();

        if ($billings->isEmpty()) {
            throw new Exception("Tagihan tidak ditemukan atau sudah lunas.");
        }

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
            'callbacks' => [
                'finish' => 'https://kpspam.finish/payment',
            ],
        ];

        // Semua metode pembayaran akan ditampilkan oleh Midtrans
        // (Tidak ada filter enabled_payments agar tidak terjadi error 'No payment channels available')

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        $isProduction = (bool) env('MIDTRANS_IS_PRODUCTION', false);
        $snapBaseUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v2/vtweb/'
            : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/';

        return [
            'redirect_url' => $snapBaseUrl . $snapToken,
            'snap_token' => $snapToken,
            'order_id' => $orderId,
            'total_bayar' => $totalAmount
        ];
    }

    public function processCashPayment($data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $billings = Billing::whereIn('id', $data['billing_ids'])->where('status', '!=', 'lunas')->get();
            $totalAmount = $billings->sum('totalTagihan');

            if (empty($billings)) {
                throw new Exception("Tagihan tidak ditemukan.");
            }

            // 1. Ubah status semua tagihan menjadi lunas
            $payments = [];
            foreach ($billings as $bill) {
                if ($bill->status !== 'lunas') {
                    $bill->update(['status' => 'lunas']);

                    // 2. Buat record Payment per tagihan karena skema mewajibkan 'billing_id'
                    $payments[] = $this->model->create([
                        'billing_id' => $bill->id,
                        'user_id' => Auth::id(), // Petugas yang memproses pembayaran
                        'nominalPembayaran' => $bill->totalTagihan,
                        'metodePembayaran' => 'tunai',
                        'tanggalBayar' => now()
                    ]);
                }
            }

            return ['payments' => $payments];
        });
    }

    public function syncMidtrans($orderId)
    {
        /** @var mixed $statusResponse */
        $statusResponse = \Midtrans\Transaction::status($orderId);
        $transactionStatus = $statusResponse->transaction_status;

        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($orderId) {
                $billings = Billing::where('midtrans_order_id', $orderId)->get();
                $processed = false;

                foreach ($billings as $bill) {
                    if ($bill->status !== 'lunas') {
                        $bill->update(['status' => 'lunas']);

                        $this->model->create([
                            'billing_id' => $bill->id,
                            'user_id' => $bill->user_id,
                            'metodePembayaran' => 'non_tunai',
                            'nominalPembayaran' => $bill->totalTagihan,
                            'tanggalBayar' => now()
                        ]);
                        $processed = true;
                    }
                }
                return $processed;
            });
        }
        
        return false;
    }
}
