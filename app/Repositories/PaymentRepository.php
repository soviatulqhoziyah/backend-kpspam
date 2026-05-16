<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Billing;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Auth;

class PaymentRepository
{

    protected $model;

    public function __construct(Payment $model)
    {
        $this->model = $model;
    }

    public function processPayment($data)
    {
        return DB::transaction(function () use ($data) {
            $billing = Billing::findOrFail($data['billing_id']);

            if ($billing->status === 'lunas') {
                throw new Exception("Tagihan periode ini sudah lunas.");
            }

            if ($data['nominalPembayaran'] < $billing->totalTagihan) {
                throw new Exception("Uang tidak cukup. Total tagihan: " . $billing->totalTagihan);
            }

            // Update status tagihan
            $billing->update(['status' => 'lunas']);

            // Ambil info siapa yang memproses
            $processor = Auth::id();

            return $this->model->create([
                'billing_id' => $data['billing_id'],
                'user_id' => $processor, // Menyimpan siapa yang menekan tombol "Bayar"
                'metodePembayaran' => $data['metodePembayaran'],
                'nominalPembayaran' => $data['nominalPembayaran'],
                'tanggalBayar' => now(),
                // Tip: Kamu bisa tambah kolom 'keterangan' di migration jika ingin 
                // mencatat misal: "Dikonfirmasi oleh petugas" atau "Bayar Mandiri"
            ]);
        });
    }
}
