<?php

namespace App\Repositories;

use App\Models\Billing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PelangganRepository
{
    public function getUnpaidBillings($userId)
    {
        Carbon::setLocale('id');

        // 1. Ambil semua tagihan menunggak, urutkan dari ID terkecil (Paling Lama)
        $billings = Billing::where('user_id', $userId)
            ->where('status', 'menunggak')
            ->orderBy('id', 'asc') // <--- PERBAIKAN DI SINI (Lama ke Baru)
            ->get();

        // 2. Mapping data agar sesuai dengan UI
        $listTagihan = $billings->map(function ($bill) {
            // Asumsi jatuh tempo adalah tanggal 20 pada bulan tagihan tersebut dibuat
            $dueDate = Carbon::parse($bill->created_at)->day(20);
            $now = Carbon::now();

            // Hitung selisih hari jika sudah melewati jatuh tempo
            $isOverdue = $now->greaterThan($dueDate);
            $daysOverdue = $isOverdue ? intval($dueDate->diffInDays($now)) : 0;

            return [
                'id' => $bill->id,
                'periode' => $bill->periode,
                'nominal' => (float) $bill->totalTagihan,
                'jatuh_tempo' => "Jatuh tempo: 20 " . $bill->periode,
                'keterangan_telat' => $isOverdue ? "Terlambat $daysOverdue hari" : "Tagihan Berjalan",
                'is_overdue' => $isOverdue
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

        // MENCARI: Ambil data payment yang mana BILLING-nya punya si user ini
        $query = \App\Models\Payment::with('billing')
            ->whereHas('billing', function ($q) use ($userId) {
                $q->where('user_id', $userId); // Filter berdasarkan pemilik tagihan
            });

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('billing', function ($q) use ($search) {
                $q->where('periode', 'like', '%' . $search . '%');
            });
        }

        $payments = $query->latest('tanggalBayar')->get();

        return $payments->map(function ($pay) {
            return [
                'id_payment' => $pay->id,
                'periode' => $pay->billing->periode,
                'tanggal_bayar_formatted' => "Dibayar pada " . \Carbon\Carbon::parse($pay->tanggalBayar)->translatedFormat('d M Y'),
                'total_bayar' => (float) $pay->nominalPembayaran,
                'status' => 'LUNAS',
                'download_url' => url("/api/payments/receipt/" . $pay->id)
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
            // Logika konversi status DB ke Step UI (1-4)
            $step = 1;
            $statusTeks = "LAPORAN TERKIRIM";

            if ($item->status == 'belumProses') {
                $step = 1;
                $statusTeks = "LAPORAN TERKIRIM";
            } elseif ($item->status == 'proses') {
                $step = 3; // Kita langsung ke step 3 (Sedang Diperbaiki)
                $statusTeks = "SEDANG DIPERBAIKI";
            } elseif ($item->status == 'selesai') {
                $step = 4;
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
                'foto_bukti' => asset('storage/' . $item->fotoBukti),
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
