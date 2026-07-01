<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\Complaint;
use App\Models\Billing;
use App\Models\User;
use App\Services\SupabaseStorage;
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

        // Piutang terbayar: tagihan bulan SEBELUMNYA yang baru dibayar bulan ini
        $pemasukanPiutang = Payment::whereMonth('tanggalBayar', $month)
            ->whereYear('tanggalBayar', $year)
            ->whereHas('billing', function ($q) use ($month, $year) {
                $q->where(function ($inner) use ($month, $year) {
                    $inner->whereYear('created_at', '<', $year)
                        ->orWhere(function ($q2) use ($month, $year) {
                            $q2->whereYear('created_at', $year)
                               ->whereMonth('created_at', '<', $month);
                        });
                });
            })
            ->sum('nominalPembayaran');

        // Tagihan tertunda: tagihan bulan ini yang belum lunas
        $tagihanTertunda = Billing::where('status', '!=', 'lunas')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('totalTagihan');
        $pengeluaranKotor = Expense::where('status', 'approve')->whereMonth('tanggalPengeluaran', $month)->whereYear('tanggalPengeluaran', $year)->sum('nominal');
        $pemasukanNonTunai = Payment::where('metodePembayaran', 'non_tunai')->whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)->sum('nominalPembayaran');
        $pemasukanTunai = Payment::where('metodePembayaran', 'tunai')->whereMonth('tanggalBayar', $month)->whereYear('tanggalBayar', $year)->sum('nominalPembayaran');

        // Pengeluaran yang sudah di-approve per petugas bulan ini (fallback jika belum ada setoran sebelumnya)
        $approvedExpensesByPetugas = Expense::where('status', 'approve')
            ->whereMonth('tanggalPengeluaran', $month)
            ->whereYear('tanggalPengeluaran', $year)
            ->select('user_id', DB::raw('SUM(nominal) as total_expense'))
            ->groupBy('user_id')
            ->pluck('total_expense', 'user_id');

        // Waktu konfirmasi setoran terakhir per petugas — pengeluaran sebelum waktu ini sudah dipotong
        $lastConfirmedByPetugas = Payment::where('metodePembayaran', 'tunai')
            ->where('is_confirmed', 1)
            ->whereMonth('tanggalBayar', $month)
            ->whereYear('tanggalBayar', $year)
            ->select('user_id', DB::raw('MAX(confirmed_at) as last_confirmed'))
            ->groupBy('user_id')
            ->pluck('last_confirmed', 'user_id');

        $setoranPetugas = Payment::with('user')
            ->where('metodePembayaran', 'tunai')
            ->where('is_confirmed', 0)
            ->whereMonth('tanggalBayar', $month)
            ->whereYear('tanggalBayar', $year)
            ->select('user_id', DB::raw('SUM(nominalPembayaran) as total_tunai'))
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) use ($approvedExpensesByPetugas, $lastConfirmedByPetugas, $month, $year) {
                $totalTunai = (float) $item->total_tunai;
                $lastConfirmed = $lastConfirmedByPetugas[$item->user_id] ?? null;

                if ($lastConfirmed) {
                    // Hanya hitung pengeluaran yang di-approve SETELAH setoran terakhir dikonfirmasi
                    $totalPengeluaran = (float) Expense::where('user_id', $item->user_id)
                        ->where('status', 'approve')
                        ->where('updated_at', '>', $lastConfirmed)
                        ->whereMonth('tanggalPengeluaran', $month)
                        ->whereYear('tanggalPengeluaran', $year)
                        ->sum('nominal');
                } else {
                    $totalPengeluaran = (float) ($approvedExpensesByPetugas[$item->user_id] ?? 0);
                }

                $selisih = $totalTunai - $totalPengeluaran;
                $perluReimburse = $selisih < 0;

                return [
                    'petugas_id' => $item->user_id,
                    'nama_petugas' => $item->user->namaLengkap ?? 'N/A',
                    'id_display' => "PTG-" . str_pad($item->user_id, 3, '0', STR_PAD_LEFT),
                    'wilayah' => ($item->user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_uang_tunai' => $totalTunai,
                    'total_pengeluaran_approved' => $totalPengeluaran,
                    'total_yang_disetor' => $perluReimburse ? 0 : $selisih,
                    'reimburse' => $perluReimburse ? abs($selisih) : 0,
                    'status' => $perluReimburse ? 'PERLU REIMBURSE' : 'BELUM DISETOR'
                ];
            });

        // Setoran yang sudah dikonfirmasi bulan ini — tiap event konfirmasi = baris terpisah
        $confirmedBatchesRaw = Payment::with('user')
            ->where('metodePembayaran', 'tunai')
            ->where('is_confirmed', 1)
            ->whereNotNull('confirmed_at')
            ->whereMonth('confirmed_at', $month)
            ->whereYear('confirmed_at', $year)
            ->select('user_id', 'confirmed_at', DB::raw('SUM(nominalPembayaran) as total_tunai'), DB::raw('COUNT(*) as jumlah_transaksi'))
            ->groupBy('user_id', 'confirmed_at')
            ->orderBy('user_id')
            ->orderBy('confirmed_at') // urutan kronologis per user untuk menemukan batch sebelumnya
            ->get();

        // Kumpulkan timestamp per user (sudah urut ASC) untuk mengetahui window expense tiap batch
        $userBatchTimestamps = [];
        foreach ($confirmedBatchesRaw as $batch) {
            $userBatchTimestamps[$batch->user_id][] = $batch->confirmed_at;
        }

        $riwayatSetoran = $confirmedBatchesRaw
            ->sortByDesc('confirmed_at')
            ->map(function ($item) use ($userBatchTimestamps, $month, $year) {
                $uid = $item->user_id;
                $timestamps = $userBatchTimestamps[$uid] ?? [];

                // Cari confirmed_at batch SEBELUMNYA untuk menentukan window expense batch ini
                $prevConfirmedAt = null;
                foreach ($timestamps as $ts) {
                    if ($ts < $item->confirmed_at) {
                        $prevConfirmedAt = $ts;
                    }
                }

                // Pengeluaran yang masuk di window batch ini: approved antara prev dan this confirmation
                $expenseQuery = Expense::where('user_id', $uid)
                    ->where('status', 'approve')
                    ->whereMonth('tanggalPengeluaran', $month)
                    ->whereYear('tanggalPengeluaran', $year)
                    ->where('updated_at', '<=', $item->confirmed_at);
                if ($prevConfirmedAt) {
                    $expenseQuery->where('updated_at', '>', $prevConfirmedAt);
                }
                $totalPengeluaran = (float) $expenseQuery->sum('nominal');

                $totalTunai = (float) $item->total_tunai;
                $totalYangDisetor = max(0, $totalTunai - $totalPengeluaran);

                return [
                    'petugas_id'         => $uid,
                    'nama_petugas'       => $item->user->namaLengkap ?? 'N/A',
                    'id_display'         => 'PTG-' . str_pad($uid, 3, '0', STR_PAD_LEFT),
                    'wilayah'            => ($item->user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_tunai'        => $totalTunai,
                    'total_pengeluaran'  => $totalPengeluaran,
                    'total_yang_disetor' => $totalYangDisetor,
                    'jumlah_transaksi'   => (int) $item->jumlah_transaksi,
                    'dikonfirmasi_pada'  => $item->confirmed_at,
                ];
            })->values();

        // Pembayaran sudah lunas (tunai atau non_tunai sudah dikonfirmasi Midtrans)
        $completedPayments = Payment::with(['billing.user'])
            ->whereMonth('tanggalBayar', $month)
            ->whereYear('tanggalBayar', $year)
            ->latest('tanggalBayar')
            ->get()
            ->map(function ($pay) {
                $user = $pay->billing->user ?? null;
                return [
                    'nama_pelanggan' => $user->namaLengkap ?? 'N/A',
                    'alamat' => ($user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_pembayaran' => (float) $pay->nominalPembayaran,
                    'metode' => strtoupper($pay->metodePembayaran),
                    'status' => 'LUNAS',
                    '_sort' => $pay->tanggalBayar,
                ];
            });

        // Tagihan Midtrans yang sudah dikirim ke pelanggan tapi belum dibayar
        $pendingMidtrans = Billing::with('user')
            ->whereNotNull('midtrans_order_id')
            ->where('status', 'menunggak')
            ->whereMonth('updated_at', $month)
            ->whereYear('updated_at', $year)
            ->get()
            ->map(function ($bill) {
                $user = $bill->user ?? null;
                return [
                    'nama_pelanggan' => $user->namaLengkap ?? 'N/A',
                    'alamat' => ($user->alamat ?? '') == 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'total_pembayaran' => (float) $bill->totalTagihan,
                    'metode' => 'NON_TUNAI',
                    'status' => 'MENUNGGU',
                    '_sort' => $bill->updated_at,
                ];
            });

        $riwayatPembayaran = $completedPayments
            ->concat($pendingMidtrans)
            ->sortByDesc('_sort')
            ->values()
            ->map(function ($item) {
                unset($item['_sort']);
                return $item;
            });

        // Pelanggan aktif yang belum dicatat meter bulan ini
        \Carbon\Carbon::setLocale('id');
        $periode = \Carbon\Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y');
        $belumDicatat = User::where('role', 'pelanggan')
            ->where('status', 'aktif')
            ->whereDoesntHave('billings', fn($q) => $q->where('periode', $periode))
            ->get()
            ->map(fn($user) => [
                'id'         => $user->id,
                'id_display' => 'PLG-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'nama'       => $user->namaLengkap,
                'wilayah'    => $user->alamat === 'talbar' ? 'Talang Barat' : 'Talang Timur',
                'no_telepon' => $user->noTelepon ?? '-',
            ])
            ->values();

        // Pelanggan dengan tunggakan >= 3 bulan (tidak terikat filter bulan/tahun)
        $tunggakanPelanggan = User::where('role', 'pelanggan')
            ->withCount(['billings' => fn($q) => $q->where('status', 'menunggak')])
            ->having('billings_count', '>=', 3)
            ->get()
            ->map(function ($user) {
                $billings = $user->billings()->where('status', 'menunggak')->orderBy('periode')->get();
                return [
                    'id'               => $user->id,
                    'id_display'       => 'PLG-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'nama'             => $user->namaLengkap,
                    'wilayah'          => $user->alamat === 'talbar' ? 'Talang Barat' : 'Talang Timur',
                    'no_telepon'       => $user->noTelepon,
                    'jumlah_tunggakan' => $billings->count(),
                    'total_tunggakan'  => (float) $billings->sum('totalTagihan'),
                    'periode_list'     => $billings->pluck('periode')->toArray(),
                ];
            })
            ->sortByDesc('jumlah_tunggakan')
            ->values();

        return [
            'stats' => [
                'pemasukan_kotor' => (float) $pemasukanKotor,
                'pemasukan_piutang' => (float) $pemasukanPiutang,
                'tagihan_tertunda' => (float) $tagihanTertunda,
                'pengeluaran_kotor' => (float) $pengeluaranKotor,
                'saldo_bersih' => (float) ($pemasukanKotor - $pengeluaranKotor),
                'non_tunai' => (float) $pemasukanNonTunai,
                'tunai' => (float) $pemasukanTunai,
            ],
            'daftar_setoran'     => $setoranPetugas,
            'riwayat_setoran'    => $riwayatSetoran,
            'riwayat_transaksi'  => $riwayatPembayaran,
            'tunggakan_pelanggan'=> $tunggakanPelanggan,
            'belum_dicatat'      => $belumDicatat,
        ];
    }

    public function confirmSetoran($petugasId)
    {
        return Payment::where('user_id', $petugasId)
            ->where('metodePembayaran', 'tunai')
            ->where('is_confirmed', 0)
            ->update([
                'is_confirmed' => 1,
                'confirmed_at' => now(),
            ]);
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
                'foto_bukti' => SupabaseStorage::buildUrl($item->fotoBukti),
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
                'foto_bukti' => SupabaseStorage::buildUrl($item->fotoBukti),
                'status' => $item->status,
                'status_label' => $item->status == 'approve' ? 'Disetujui' : ($item->status == 'reject' ? 'Ditolak' : 'Pending')
            ];
        });

        return ['summary' => $summary, 'list' => $paginated];
    }
}