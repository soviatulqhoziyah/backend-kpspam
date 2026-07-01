<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Services\SupabaseStorage;
use Illuminate\Support\Facades\Auth;

class ExpenseRepository
{

    protected $model;

    public function __construct(Expense $model)
    {
        $this->model = $model;
    }

    public function getPetugasSummary($petugasId)
    {
        \Carbon\Carbon::setLocale('id');
        $now = \Carbon\Carbon::now();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // 1. Ambil Riwayat HANYA bulan & tahun berjalan
        $expensesBulanIni = $this->model->where('user_id', $petugasId)
            ->whereMonth('tanggalPengeluaran', $currentMonth)
            ->whereYear('tanggalPengeluaran', $currentYear)
            ->latest('tanggalPengeluaran')
            ->get();

        // 2. Hitung TOTAL NOMINAL (Hanya yang sudah DISETUJUI oleh Admin)
        $totalApprove = $expensesBulanIni->where('status', 'approve')->sum('nominal');

        // 3. Hitung JUMLAH TRANSAKSI (Semua status: pending, approve, reject di bulan ini)
        $totalTransaksiCount = $expensesBulanIni->count();

        // 4. Mapping data list untuk UI
        $listData = $expensesBulanIni->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->namaPengeluaran,
                'kategori' => $item->kategori ?? 'Umum',
                'tanggal' => \Carbon\Carbon::parse($item->tanggalPengeluaran)->translatedFormat('d M Y'),
                'nominal' => (float) $item->nominal,
                'status' => strtoupper($item->status), // 'PENDING', 'APPROVE', 'REJECT'
                'foto_bukti' => SupabaseStorage::buildUrl($item->fotoBukti)
            ];
        });

        return [
            'summary' => [
                'total_pengeluaran_bulan_ini' => (float) $totalApprove,
                'jumlah_transaksi' => $totalTransaksiCount, // Kirim angka asli saja agar lebih fleksibel di frontend
                'nama_bulan' => strtoupper($now->translatedFormat('F')), // contoh: "MEI"
                'bulan_saat_ini' => $now->translatedFormat('F Y')
            ],
            'riwayat' => $listData
        ];
    }

    public function store($data, string $base64Image, string $ext)
    {
        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            throw new \Exception("Data foto tidak valid.");
        }
        $filename = 'bukti_pengeluaran_' . time() . '_' . Auth::id() . '.' . $ext;
        $fotoUrl = SupabaseStorage::upload('bukti_pengeluaran/' . $filename, $imageData, $ext);

        return $this->model->create([
            'user_id' => Auth::id(),
            'namaPengeluaran' => $data['namaPengeluaran'],
            'nominal' => $data['nominal'],
            'fotoBukti' => $fotoUrl,
            'tanggalPengeluaran' => now(),
            'status' => 'pending'
        ]);
    }

    public function updateStatus($id, $status)
    {
        $expense = $this->model->findOrFail($id);
        $expense->update(['status' => $status]);
        return $expense;
    }
}
