<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\Complaint;
use App\Models\Billing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminRepository
{
    public function getYearlySummary($year)
    {
        $year = (int) $year;
        $totalPemasukan = Payment::whereYear('tanggalBayar', $year)->sum('nominalPembayaran');
        $totalPengeluaran = Expense::whereYear('tanggalPengeluaran', $year)->where('status', 'approve')->sum('nominal');
        $saldoTersisa = $totalPemasukan - $totalPengeluaran;

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $pemasukanBulan = Payment::whereYear('tanggalBayar', $year)->whereMonth('tanggalBayar', $m)->sum('nominalPembayaran');
            $pengeluaranBulan = Expense::whereYear('tanggalPengeluaran', $year)->whereMonth('tanggalPengeluaran', $m)->where('status', 'approve')->sum('nominal');

            $monthlyData[] = [
                'bulan' => Carbon::create()->month($m)->translatedFormat('M'),
                'pemasukan' => (float) $pemasukanBulan,
                'pengeluaran' => (float) $pengeluaranBulan,
            ];
        }

        return [
            'tahun' => $year,
            'ringkasan' => [
                'total_pemasukan_kotor' => (float) $totalPemasukan,
                'total_pengeluaran_kotor' => (float) $totalPengeluaran,
                'saldo_tersisa' => (float) $saldoTersisa,
            ],
            'grafik_bulanan' => $monthlyData
        ];
    }

    public function getMonthlyDetail($month, $year)
    {
        $month = (int) $month;
        $year = (int) $year;

        $pemasukanKotor = Payment::whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)->sum('nominalPembayaran');
        $pemasukanPiutang = Billing::where('status', 'menunggak')->sum('totalTagihan');
        $pengeluaranKotor = Expense::where('status', 'approve')->whereMonth('tanggalPengeluaran', $month)->whereYear('tanggalPengeluaran', $year)->sum('nominal');
        $pemasukanNonTunai = Payment::where('metodePembayaran', 'non_tunai')->whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)->sum('nominalPembayaran');
        $pemasukanTunai = Payment::where('metodePembayaran', 'tunai')->whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)->sum('nominalPembayaran');

        $setoranPetugas = Payment::with('user')
            ->where('metodePembayaran', 'tunai')
            ->where('is_confirmed', 0)
            ->select('user_id', DB::raw('SUM(nominalPembayaran) as total_tunai'))
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'petugas_id' => $item->user_id,
                    'nama_petugas' => $item->user->namaLengkap ?? 'N/A',
                    'id_display' => "PTG-" . str_pad($item->user_id, 3, '0', STR_PAD_LEFT),
                    'wilayah' => ($item->user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_uang_tunai' => (float) $item->total_tunai,
                    'status' => 'BELUM DISETOR'
                ];
            });

        $riwayatPembayaran = Payment::with(['billing.user'])
            ->whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)
            ->latest()
            ->get()
            ->map(function ($pay) {
                return [
                    'nama_pelanggan' => $pay->billing->user->namaLengkap ?? 'N/A',
                    'alamat' => ($pay->billing->user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_pembayaran' => (float) $pay->nominalPembayaran,
                    'metode' => strtoupper($pay->metodePembayaran),
                    'status' => 'LUNAS'
                ];
            });

        return [
            'stats' => [
                'pemasukan_kotor' => (float) $pemasukanKotor,
                'pemasukan_piutang' => (float) $pemasukanPiutang,
                'pengeluaran_kotor' => (float) $pengeluaranKotor,
                'saldo_bersih' => (float) ($pemasukanKotor - $pengeluaranKotor),
                'non_tunai' => (float) $pemasukanNonTunai,
                'tunai' => (float) $pemasukanTunai,
            ],
            'daftar_setoran' => $setoranPetugas,
            'riwayat_transaksi' => $riwayatPembayaran
        ];
    }

    public function confirmSetoran($petugasId)
    {
        return Payment::where('user_id', $petugasId)->where('metodePembayaran', 'tunai')->where('is_confirmed', 0)->update(['is_confirmed' => 1]);
    }

    public function getComplaintManagement($request)
    {
        Carbon::setLocale('id');
        $month = (int) $request->query('month', date('m'));
        $year = (int) $request->query('year', date('Y'));

        $query = Complaint::with('user')->whereMonth('created_at', $month)->whereYear('created_at', $year);

        if ($request->has('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('namaLengkap', 'like', '%' . $request->search . '%');
            });
        }

        $summaryData = clone $query; // Clone agar count tidak terpengaruh pagination
        $allData = $summaryData->get();

        $summary = [
            'total_pengaduan' => $allData->count(),
            'belum_diproses' => $allData->where('status', 'belumProses')->count(),
            'sedang_diproses' => $allData->where('status', 'proses')->count(),
            'selesai' => $allData->where('status', 'selesai')->count(),
        ];

        $paginated = $query->latest()->paginate(10);
        $paginated->getCollection()->transform(function ($item) {
            $nama = $item->user->namaLengkap ?? 'User Terhapus';
            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->created_at)->translatedFormat('d M Y'),
                'nama_pelanggan' => $nama,
                'inisial' => collect(explode(' ', $nama))->map(fn($n) => $n[0])->take(2)->join(''),
                'kategori' => $item->kategori ?? 'Lainnya',
                'deskripsi_singkat' => \Illuminate\Support\Str::limit($item->deskripsi, 30),
                'foto_bukti' => asset('storage/' . $item->fotoBukti),
                'status' => strtoupper($item->status),
            ];
        });

        return ['summary' => $summary, 'list' => $paginated];
    }

    public function getExpenseAudit($request)
    {
        Carbon::setLocale('id');
        $month = (int) $request->query('month', date('m'));
        $year = (int) $request->query('year', date('Y'));

        // PERBAIKAN: Definisikan $query terlebih dahulu sebelum difilter search!
        $query = Expense::with('user')
            ->whereMonth('tanggalPengeluaran', $month)
            ->whereYear('tanggalPengeluaran', $year);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('namaPengeluaran', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('namaLengkap', 'like', '%' . $search . '%');
                    });
            });
        }

        $summaryData = clone $query;
        $allData = $summaryData->get();

        $summary = [
            'total_disetujui' => (float) $allData->where('status', 'approve')->sum('nominal'),
            'menunggu_validasi' => $allData->where('status', 'pending')->count(),
            'bulan_label' => Carbon::create()->month($month)->translatedFormat('F')
        ];

        $paginated = $query->latest('tanggalPengeluaran')->paginate(10);
        $paginated->getCollection()->transform(function ($item) {
            $nama = $item->user->namaLengkap ?? 'Petugas Terhapus';
            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->tanggalPengeluaran)->translatedFormat('d M Y'),
                'nama_petugas' => $nama,
                'inisial' => collect(explode(' ', $nama))->map(fn($n) => $n[0])->take(2)->join(''),
                'deskripsi' => $item->namaPengeluaran,
                'nominal' => (float) $item->nominal,
                'foto_bukti' => asset('storage/' . $item->fotoBukti),
                'status' => $item->status,
                'status_label' => $item->status == 'approve' ? 'Disetujui' : ($item->status == 'reject' ? 'Ditolak' : 'Pending')
            ];
        });

        return ['summary' => $summary, 'list' => $paginated];
    }
}