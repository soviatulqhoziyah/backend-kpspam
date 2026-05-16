<?php

namespace App\Repositories;

use App\Models\Billing;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserRepository {

    protected $model;

    public function __construct(User $model) {
        $this->model = $model;
    }

    public function getAllUsers() {
        return $this->model->latest()->get();
    }

    public function storeUser($data) {
        $data['password'] = Hash::make($data['password']);
        return $this->model->create($data);
    }

    public function updateUser($id, $data) {
        $user = $this->model->findOrFail($id);
        
        // Jika password diisi, maka hash. Jika kosong, hapus dari array agar tidak terupdate jadi kosong
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $user;
    }

    public function deleteUser($id) {
        $user = $this->model->findOrFail($id);
        return $user->delete();
    }
}

class PetugasRepository {

    public function getDashboardData($petugasId)
    {
        // Set locale ke Indonesia untuk nama bulan
        Carbon::setLocale('id');
        $currentMonth = Carbon::now()->translatedFormat('F Y'); 
        
        // 1. Hitung Target Pelanggan (Hanya role 'pelanggan' DAN status 'aktif')
        $totalPelangganAktif = User::where('role', 'pelanggan')
                                   ->where('status', 'aktif') 
                                   ->count();

        // 2. Hitung Rumah Dikunjungi (Berapa banyak billing yang dibuat bulan ini)
        // Kita asumsikan 1 billing = 1 kunjungan sukses
        $rumahDikunjungi = Billing::where('periode', $currentMonth)->count();
        
        $sisaKunjungan = $totalPelangganAktif - $rumahDikunjungi;
        
        // Jaga agar sisa kunjungan tidak minus jika ada data lama
        $sisaKunjungan = $sisaKunjungan < 0 ? 0 : $sisaKunjungan;

        // Hitung Persentase Progres
        $progres = $totalPelangganAktif > 0 ? round(($rumahDikunjungi / $totalPelangganAktif) * 100) : 0;

        // 3. Hitung Keuangan (Pemasukan yang dikumpulkan petugas ini di bulan berjalan)
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

        // 4. Hitung Pengeluaran Petugas ini (hanya yang sudah approve)
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
}