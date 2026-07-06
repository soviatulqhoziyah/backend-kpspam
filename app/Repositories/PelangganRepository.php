<?php

namespace App\Repositories;

use App\Models\Billing;
use App\Models\Payment;
use App\Services\SupabaseStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PelangganRepository
{
    public function getUnpaidBillings($userId)
    {
        Carbon::setLocale('id');

        // Sertakan akun lama dengan no_kk yang sama agar piutang ikut tampil
        $currentUser = \App\Models\User::find($userId);
        $relatedUserIds = collect([$userId]);
        if ($currentUser && $currentUser->no_kk) {
            $oldIds = \App\Models\User::where('no_kk', $currentUser->no_kk)
                ->where('id', '!=', $userId)
                ->pluck('id');
            $relatedUserIds = $relatedUserIds->merge($oldIds);
        }

        // Auto-sync billing yang punya midtrans_order_id tapi belum lunas
        // Ini memastikan status diperbarui meski WebView sudah ditutup sebelumnya
        $pendingMidtrans = Billing::whereIn('user_id', $relatedUserIds)
            ->where('status', 'menunggak')
            ->whereNotNull('midtrans_order_id')
            ->get();

        foreach ($pendingMidtrans as $bill) {
            try {
                \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
                \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
                $statusResponse = \Midtrans\Transaction::status($bill->midtrans_order_id);
                $txStatus = $statusResponse->transaction_status ?? '';

                if ($txStatus === 'settlement' || $txStatus === 'capture') {
                    // Pastikan payment belum pernah dibuat untuk billing ini
                    $sudahAda = Payment::where('billing_id', $bill->id)->exists();
                    if (!$sudahAda) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($bill) {
                            $bill->update(['status' => 'lunas']);
                            Payment::create([
                                'billing_id'         => $bill->id,
                                'user_id'            => $bill->user_id,
                                'metodePembayaran'   => 'non_tunai',
                                'nominalPembayaran'  => $bill->totalTagihan,
                                'tanggalBayar'       => now(),
                            ]);
                        });
                    } else {
                        // Payment sudah ada tapi billing belum lunas — sinkronkan statusnya saja
                        $bill->update(['status' => 'lunas']);
                    }
                }
            } catch (\Exception) {
                // Abaikan error — mungkin transaksi belum ada di Midtrans
            }
        }

        // Ambil semua tagihan menunggak (akun ini + akun lama no_kk sama), urutkan dari ID terkecil (Paling Lama)
        $billings = Billing::whereIn('user_id', $relatedUserIds)
            ->where('status', 'menunggak')
            ->orderBy('id', 'asc')
            ->get();

        // 2. Mapping data agar sesuai dengan UI
        $listTagihan = $billings->map(function ($bill) use ($userId) {
            $dueDate = Carbon::parse($bill->created_at)->day(20);
            $now = Carbon::now();
            $isOverdue = $now->greaterThan($dueDate);
            $isPiutang = $bill->user_id !== $userId;

            return [
                'id'               => $bill->id,
                'periode'          => $bill->periode,
                'nominal'          => (float) $bill->totalTagihan,
                'jatuh_tempo'      => "Jatuh tempo: 20 " . $bill->periode,
                'keterangan_telat' => $isPiutang ? "Piutang Tagihan Lama" : ($isOverdue ? "Segera bayar!" : "Tagihan Berjalan"),
                'is_overdue'       => $isOverdue || $isPiutang,
                'is_piutang'       => $isPiutang,
            ];
        });

        return [
            'user' => [
                'nama' => Auth::user()->namaLengkap,
                'role' => strtoupper(Auth::user()->role)
            ],
            'summary' => [
                'jumlah_tagihan_tersedia' => $billings->count() . " Tagihan Tersedia",
                'total_tunggakan_saat_ini' => (float) $billings->sum('totalTagihan')
            ],
            'daftar_tagihan' => $listTagihan
        ];
    }

    public function getPaymentHistory($userId, $request)
    {
        \Carbon\Carbon::setLocale('id');

        // Sertakan billing dari akun lama dengan no_kk yang sama (piutang yang sudah dilunasi)
        $currentUser = \App\Models\User::find($userId);
        $relatedUserIds = collect([$userId]);

        if ($currentUser && $currentUser->no_kk) {
            $oldIds = \App\Models\User::where('no_kk', $currentUser->no_kk)
                ->where('id', '!=', $userId)
                ->pluck('id');
            $relatedUserIds = $relatedUserIds->merge($oldIds);
        }

        $query = \App\Models\Payment::with('billing')
            ->whereHas('billing', function ($q) use ($relatedUserIds) {
                $q->whereIn('user_id', $relatedUserIds);
            });

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('billing', function ($q) use ($search) {
                $q->where('periode', 'like', '%' . $search . '%');
            });
        }

        $payments = $query->latest('tanggalBayar')->get();

        return $payments->map(function ($pay) use ($userId) {
            $billing = $pay->billing;
            $isPiutang = $billing && $billing->user_id !== $userId;
            return [
                'id_payment'             => $pay->id,
                'periode'                => $billing->periode,
                'tanggal_bayar_formatted'=> "Dibayar pada " . \Carbon\Carbon::parse($pay->tanggalBayar)->translatedFormat('d M Y'),
                'total_bayar'            => (float) $pay->nominalPembayaran,
                'status'                 => 'LUNAS',
                'metode'                 => $pay->metodePembayaran === 'tunai' ? 'Tunai' : 'Non-Tunai (QRIS)',
                'meteran_lalu'           => (int) ($billing->meteranLalu ?? 0),
                'meteran_sekarang'       => (int) ($billing->meteranSekarang ?? 0),
                'pemakaian'              => (int) ($billing->jumlahPemakaian ?? 0),
                'foto_meteran'           => $billing->fotoMeteran ? SupabaseStorage::buildUrl($billing->fotoMeteran) : null,
                'download_url'           => null,
                'is_piutang'             => $isPiutang,
                'keterangan'             => $isPiutang ? 'Pelunasan Piutang' : null,
            ];
        });
    }

    public function getMyComplaints($userId)
    {
        \Carbon\Carbon::setLocale('id');

        // 1. Ambil semua pengaduan milik pelanggan ini
        $complaints = \App\Models\Complaint::where('user_id', $userId)
            ->latest()
            ->get();

        // 2. Hitung jumlah laporan yang masih berjalan (belum selesai)
        $activeCount = $complaints->where('status', '!=', 'selesai')->count();

        // 3. Mapping data untuk Stepper UI
        $dataMapped = $complaints->map(function ($item) {
            // Logika konversi status DB ke Step UI (1-3)
            $step = 1;
            $statusTeks = "LAPORAN TERKIRIM";

            if ($item->status == 'belumProses') {
                $step = 1;
                $statusTeks = "LAPORAN TERKIRIM";
            } elseif ($item->status == 'proses') {
                $step = 2;
                $statusTeks = "SEDANG DIPERBAIKI";
            } elseif ($item->status == 'selesai') {
                $step = 3;
                $statusTeks = "SELESAI";
            }

            return [
                'id_display' => "#TK-" . str_pad($item->id, 5, '0', STR_PAD_LEFT),
                'id_asli' => $item->id,
                'tanggal_formatted' => \Carbon\Carbon::parse($item->created_at)->translatedFormat('d M Y • H:i') . " WIB",
                'deskripsi' => $item->deskripsi,
                'status_db' => $item->status,
                'status_label' => $statusTeks,
                'current_step' => $step, // Kirim angka 1-4 untuk Stepper di Flutter
                'foto_bukti' => SupabaseStorage::buildUrl($item->fotoBukti),
            ];
        });

        return [
            'summary' => [
                'laporan_aktif_count' => $activeCount . " BERJALAN",
            ],
            'daftar_pengaduan' => $dataMapped,
        ];
    }

    public function getProfileData($userId)
    {
        // 1. Ambil data user
        $user = \App\Models\User::findOrFail($userId);

        // 2. Mapping data untuk UI
        return [
            // Ambil foto dari storage, jika kosong pakai UI-Avatars
            'foto_url' => $user->foto
                ? asset('storage/' . $user->foto)
                : 'https://ui-avatars.com/api/?name=' . urlencode($user->namaLengkap) . '&background=0D8ABC&color=fff',

            'nama_lengkap' => $user->namaLengkap,
            'role' => 'Pelanggan',
            'informasi_personal' => [
                // Di UI tertulis ID Pegawai, untuk pelanggan kita sebut ID Pelanggan
                'id_pelanggan' => "#PLG-" . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                'nomor_telepon' => $user->noTelepon,
                'alamat_lengkap' => $user->alamat == 'talbar' ? 'Talang Barat' : 'Talang Timur',
            ],
            'akun' => [
                'username' => $user->username,
                'status' => strtoupper($user->status), // AKTIF
            ]
        ];
    }
}
