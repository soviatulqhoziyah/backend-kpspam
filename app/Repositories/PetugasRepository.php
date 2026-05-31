<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PetugasRepository
{
    public function getDashboardData($petugasId)
    {
        Carbon::setLocale('id');
        $currentMonth = Carbon::now()->translatedFormat('F Y');

        $totalPelangganAktif = User::where('role', 'pelanggan')
            ->where('status', 'aktif')
            ->count();

        $rumahDikunjungi = Billing::where('periode', $currentMonth)->count();
        $sisaKunjungan = $totalPelangganAktif - $rumahDikunjungi;
        $sisaKunjungan = $sisaKunjungan < 0 ? 0 : $sisaKunjungan;

        $progres = $totalPelangganAktif > 0 ? round(($rumahDikunjungi / $totalPelangganAktif) * 100) : 0;

        $pemasukanTunai = Payment::where('user_id', $petugasId)
            ->where('metodePembayaran', 'tunai')
            ->whereMonth('tanggalBayar', Carbon::now()->month)
            ->whereYear('tanggalBayar', Carbon::now()->year)
            ->sum('nominalPembayaran');

        $pemasukanNonTunai = Payment::where('user_id', $petugasId)
            ->where('metodePembayaran', 'non_tunai')
            ->whereMonth('tanggalBayar', Carbon::now()->month)
            ->whereYear('tanggalBayar', Carbon::now()->year)
            ->sum('nominalPembayaran');

        $totalSaldo = $pemasukanTunai + $pemasukanNonTunai;

        $totalPengeluaran = Expense::where('user_id', $petugasId)
            ->where('status', 'approve')
            ->whereMonth('tanggalPengeluaran', Carbon::now()->month)
            ->whereYear('tanggalPengeluaran', Carbon::now()->year)
            ->sum('nominal');

        return [
            'nama_petugas' => Auth::user()->namaLengkap,
            'periode' => $currentMonth,
            'statistik' => [
                'total_target' => $totalPelangganAktif,
                'rumah_dikunjungi' => $rumahDikunjungi,
                'sisa_kunjungan' => $sisaKunjungan,
                'progres_persen' => $progres,
            ],
            'keuangan' => [
                'total_saldo' => (float) $totalSaldo,
                'tunai' => (float) $pemasukanTunai,
                'non_tunai' => (float) $pemasukanNonTunai,
            ],
            'pengeluaran_bulan_ini' => (float) $totalPengeluaran
        ];
    }

    public function getCustomerList($request)
    {
        Carbon::setLocale('id');
        $currentMonth = Carbon::now()->translatedFormat('F Y');

        // 1. Ambil data dasar
        $query = User::where('role', 'pelanggan')->where('status', 'aktif');

        // 2. Filter Wilayah (Jorong)
        if ($request->has('wilayah') && $request->wilayah != 'Semua') {
            $query->where('alamat', $request->wilayah == 'Talang Barat' ? 'talbar' : 'taltim');
        }

        // 3. Filter Pencarian Nama
        if ($request->has('search')) {
            $query->where('namaLengkap', 'like', '%' . $request->search . '%');
        }

        $users = $query->get();

        // 4. Mapping Status (Cek satu-satu statusnya)
        $collection = $users->map(function ($user) use ($currentMonth) {
            $billingBulanIni = Billing::where('user_id', $user->id)
                ->where('periode', $currentMonth)
                ->first();

            $adaTunggakan = Billing::where('user_id', $user->id)
                ->where('status', 'menunggak')
                ->exists();

            $lastMeterRecord = Billing::where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();

            // Tentukan label status
            $statusLabel = "Belum Dicatat";
            if ($billingBulanIni) {
                $statusLabel = "Sudah Dicatat";
            }
            // Jika ada tunggakan, statusnya jadi menunggak (prioritas di UI)
            if ($adaTunggakan) {
                $statusLabel = "Menunggak";
            }

            return [
                'id' => $user->id,
                'nama' => $user->namaLengkap,
                'alamat' => $user->alamat == 'talbar' ? 'Jorong Talang Barat' : 'Jorong Talang Timur',
                'meter_terakhir' => $lastMeterRecord ? $lastMeterRecord->meteranSekarang : 0,
                'status_tag' => $statusLabel,
                'sudah_dicatat_bulan_ini' => $billingBulanIni ? true : false
            ];
        });

        // 5. Hitung Summary (untuk angka di atas tab) - sebelum difilter status
        $summary = [
            'belum_dicatat' => $collection->where('status_tag', 'Belum Dicatat')->count(),
            'sudah_dicatat' => $collection->where('status_tag', 'Sudah Dicatat')->count(),
            'menunggak' => $collection->where('status_tag', 'Menunggak')->count(),
        ];

        // 6. FILTER STATUS (Ini bagian yang kamu minta)
        // Jika di URL ada ?status=Sudah Dicatat, maka kita saring
        if ($request->has('status') && $request->status != 'Semua') {
            $collection = $collection->where('status_tag', $request->status);
        }

        return [
            'periode' => $currentMonth,
            'summary' => $summary,
            'pelanggan' => $collection->values()
        ];
    }

    public function getCustomerDetail($id)
    {
        // 1. Cari user berdasarkan ID
        $user = User::where('role', 'pelanggan')->findOrFail($id);

        // Cari meteran terakhir
        $lastMeterRecord = Billing::where('user_id', $id)
            ->orderBy('id', 'desc')
            ->first();
        $meterTerakhir = $lastMeterRecord ? $lastMeterRecord->meteranSekarang : 0;

        // 2. Ambil semua tagihan yang statusnya 'menunggak'
        $tunggakan = Billing::where('user_id', $id)
            ->where('status', 'menunggak')
            ->orderBy('id', 'asc') // Urutkan dari yang paling lama
            ->get();

        // 3. Hitung total uang yang menunggak
        $totalTunggakan = $tunggakan->sum('totalTagihan');

        // 4. Mapping data untuk tampilan UI
        $listTunggakan = $tunggakan->map(function ($bill) {
            return [
                'id_billing' => $bill->id,
                'periode' => $bill->periode,
                // Asumsi jatuh tempo adalah tanggal 20 setiap bulannya (bisa disesuaikan)
                'jatuh_tempo' => '20 ' . $bill->periode,
                'nominal' => (float) $bill->totalTagihan,
                'status' => 'MENUNGGAK'
            ];
        });

        return [
            'profil' => [
                'id' => $user->id,
                'nama' => $user->namaLengkap,
                'id_pelanggan' => $user->username, // Menggunakan username sebagai nomor ID di kartu
                'status' => strtoupper($user->status),
                'alamat' => $user->alamat == 'talbar' ? 'Jorong Talang Barat' : 'Jorong Talang Timur',
                'meter_terakhir' => $meterTerakhir,
            ],
            'tunggakan_info' => [
                'jumlah_bulan' => $tunggakan->count() . ' Bulan Menunggak',
                'total_tunggakan' => (float) $totalTunggakan,
                'daftar_tagihan' => $listTunggakan
            ]
        ];
    }

    public function getProfileData($petugasId)
    {
        $user = User::findOrFail($petugasId);
        $totalTugas = Payment::count();

        return [
            // Jika foto kosong, berikan foto default (UI tetap cantik)
            'foto_url' => $user->foto
                ? asset('storage/' . $user->foto)
                : 'https://ui-avatars.com/api/?name=' . urlencode($user->namaLengkap) . '&background=0D8ABC&color=fff',

            'nama_lengkap' => $user->namaLengkap,
            'role' => 'Petugas Lapangan',
            'statistik' => [
                'total_tugas' => $totalTugas,
            ],
            'informasi_personal' => [
                'id_pegawai' => "PTG-" . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'nomor_telepon' => $user->noTelepon,
            ]
        ];
    }
}
