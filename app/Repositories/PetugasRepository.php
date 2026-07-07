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

        $progres = $totalPelangganAktif > 0 ? min(100, round(($rumahDikunjungi / $totalPelangganAktif) * 100)) : 0;

        $pemasukanTunai = Payment::where('user_id', $petugasId)
            ->where('metodePembayaran', 'tunai')
            ->whereMonth('tanggalBayar', Carbon::now()->month)
            ->whereYear('tanggalBayar', Carbon::now()->year)
            ->sum('nominalPembayaran');

        // Non-tunai dikelola sistem (Midtrans), user_id-nya adalah pelanggan bukan petugas
        // Tampilkan total non-tunai bulan ini lintas semua pelanggan
        $pemasukanNonTunai = Payment::where('metodePembayaran', 'non_tunai')
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

        $users = $query->with('billings')->get();

        // 4. Mapping Status — pakai in-memory dari relasi yang sudah di-eager load
        $collection = $users->map(function ($user) use ($currentMonth) {
            $billingBulanIni = $user->billings
                ->where('periode', $currentMonth)
                ->first();

            $adaTunggakan = $user->billings
                ->where('status', 'menunggak')
                ->isNotEmpty();

            $lastMeterRecord = $user->billings
                ->sortByDesc('id')
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
                'meter_terakhir' => $lastMeterRecord ? $lastMeterRecord->meteranSekarang : (int) ($user->meteranAwal ?? 0),
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
        $meterTerakhir = $lastMeterRecord ? $lastMeterRecord->meteranSekarang : (int) ($user->meteranAwal ?? 0);

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

    public function getRiwayatBulanan($month, $year, $wilayah = null, $status = null)
    {
        Carbon::setLocale('id');
        $periode = Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y');

        // Query semua billing bulan ini untuk ringkasan (tanpa filter)
        $allBillings = Billing::with('user')->where('periode', $periode)->get();
        // Hitung pelanggan yang sudah ada pada akhir bulan tersebut (bukan count saat ini)
        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $totalPelangganAktif = User::where('role', 'pelanggan')
            ->where('status', 'aktif')
            ->where('created_at', '<=', $endOfMonth)
            ->count();
        $totalDicatat = $allBillings->count();
        $totalTagihan = $allBillings->sum('totalTagihan');
        $totalLunas   = $allBillings->where('status', 'lunas')->count();
        $persen = $totalPelangganAktif > 0 ? min(100, round(($totalDicatat / $totalPelangganAktif) * 100)) : 0;

        // Query billing untuk daftar (dengan filter)
        $query = Billing::with('user')->where('periode', $periode);
        if ($status && $status !== 'Semua') {
            $query->where('status', strtolower($status));
        }
        if ($wilayah && $wilayah !== 'Semua') {
            if ($wilayah === 'Talang Barat') {
                $query->whereHas('user', fn($q) => $q->where('alamat', 'talbar'));
            } else {
                $query->whereHas('user', fn($q) => $q->where('alamat', '!=', 'talbar'));
            }
        }
        $billings = $query->orderBy('created_at', 'asc')->get();

        $daftar = $billings->map(function ($bill) {
            return [
                'id'               => $bill->id,
                'user_id'          => $bill->user_id,
                'nama_pelanggan'   => $bill->user->namaLengkap,
                'wilayah'          => $bill->user->alamat === 'talbar' ? 'Talang Barat' : 'Talang Timur',
                'meteran_lalu'     => (int) $bill->meteranLalu,
                'meteran_sekarang' => (int) $bill->meteranSekarang,
                'pemakaian'        => (int) $bill->jumlahPemakaian,
                'total_tagihan'    => (float) $bill->totalTagihan,
                'status'           => $bill->status,
                'tanggal_dicatat'  => Carbon::parse($bill->created_at)->translatedFormat('d M Y'),
                'foto_meteran'     => $bill->fotoMeteran ?? null,
            ];
        });

        return [
            'periode'   => $periode,
            'ringkasan' => [
                'total_dicatat'   => $totalDicatat,
                'total_pelanggan' => $totalPelangganAktif,
                'total_lunas'     => $totalLunas,
                'total_tagihan'   => (float) $totalTagihan,
                'persen_selesai'  => $persen,
            ],
            'daftar_billing' => $daftar,
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
