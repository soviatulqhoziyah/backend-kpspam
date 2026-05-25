<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function handleNotification(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');
        // Validasi keamanan agar bukan orang iseng yang panggil
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);
        // if ($hashed !== $request->signature_key) {
        //     return response()->json(['message' => 'Invalid signature'], 403);
        // }

        $transactionStatus = $request->transaction_status;
        $orderId = $request->order_id;

        // Jika Pembayaran Sukses (Settlement)
        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            DB::transaction(function () use ($orderId) {
                // 1. Cari semua billing yang punya order_id tersebut
                $billings = Billing::where('midtrans_order_id', $orderId)->get();

                foreach ($billings as $bill) {
                    // 2. Update status jadi LUNAS
                    $bill->update(['status' => 'lunas']);

                    // 3. Masukkan ke tabel Payments sebagai riwayat
                    Payment::create([
                        'billing_id' => $bill->id,
                        'user_id' => $bill->user_id, // Atas nama pelanggan
                        'metodePembayaran' => 'non_tunai',
                        'nominalPembayaran' => $bill->totalTagihan,
                        'tanggalBayar' => now()
                    ]);
                }
            });

            return response()->json(['message' => 'Database Updated']);
        }

        return response()->json(['message' => 'Notification Received']);
    }
}
