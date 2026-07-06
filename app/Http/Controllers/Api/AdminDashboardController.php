<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\AdminRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    protected $adminRepo;

    public function __construct(AdminRepository $adminRepo)
    {
        $this->adminRepo = $adminRepo;
    }

    public function index(Request $request)
    {
        try {
            // Cek role admin
            if (Auth::user()->role !== 'admin') {
                return $this->unauthorizedResponse("Akses khusus Administrator.");
            }

            // Ambil tahun dari request, default ke tahun sekarang
            $year = $request->query('year', date('Y'));

            $data = $this->adminRepo->getYearlySummary($year);

            return $this->successResponse($data, "Data dashboard admin berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function monthlyDetail(Request $request)
    {
        try {
            $month = $request->query('month', date('m'));
            $year = $request->query('year', date('Y'));

            $data = $this->adminRepo->getMonthlyDetail($month, $year);
            return $this->successResponse($data, "Detail transaksi bulanan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function confirmPayment($petugasId)
    {
        try {
            $petugas = User::where('role', 'petugas')->findOrFail($petugasId);
            $this->adminRepo->confirmSetoran($petugas->id);
            return $this->successResponse(null, "Setoran petugas berhasil dikonfirmasi");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function complaintIndex(Request $request)
    {
        try {
            $data = $this->adminRepo->getComplaintManagement($request);
            return $this->successResponse($data, "Daftar pengaduan berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function expenseAudit(Request $request)
    {
        try {
            $data = $this->adminRepo->getExpenseAudit($request);
            return $this->successResponse($data, "Data audit pengeluaran berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function exportRingkasanTahunan(Request $request)
    {
        $year = (int) $request->query('year', date('Y'));
        $data = $this->adminRepo->getYearlySummary($year);

        $ringkasan   = $data['ringkasan'];
        $grafik      = $data['grafik_bulanan'];

        $bulanNama = [
            1 => 'Januari', 2 => 'Februari',  3 => 'Maret',
            4 => 'April',   5 => 'Mei',        6 => 'Juni',
            7 => 'Juli',    8 => 'Agustus',    9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Tahunan');

        // Baris 1 – Judul
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', "Laporan Arus Kas Tahunan – {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B1B42']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Baris 2 – Sub-judul info
        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', "KPSPAM – Sistem MATA AIR | Periode: Januari – Desember {$year}");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Baris 3 – kosong pemisah

        // Baris 4 – Header kolom (6 kolom: tambah PEMASUKAN PIUTANG)
        $headers = ['NO.', 'BULAN', 'TOTAL PEMASUKAN', 'PEMASUKAN PIUTANG', 'TOTAL PENGELUARAN', 'SALDO BULANAN'];
        foreach ($headers as $i => $label) {
            $cell = chr(65 + $i) . '4';
            $sheet->setCellValue($cell, $label);
        }
        $sheet->getStyle('A4:F4')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // Data bulanan
        $rowNum = 5;
        foreach ($grafik as $idx => $item) {
            $namaBulan = $bulanNama[$idx + 1] ?? $item['bulan'];
            $saldoBulanan = $item['pemasukan'] - $item['pengeluaran'];
            $piutangBulan = $item['pemasukan_piutang'] ?? 0;

            $sheet->setCellValue("A{$rowNum}", $idx + 1);
            $sheet->setCellValue("B{$rowNum}", $namaBulan);
            $sheet->setCellValue("C{$rowNum}", $item['pemasukan']);
            $sheet->setCellValue("D{$rowNum}", $piutangBulan);
            $sheet->setCellValue("E{$rowNum}", $item['pengeluaran']);
            $sheet->setCellValue("F{$rowNum}", $saldoBulanan);

            $numFmt = '"Rp "#,##0';
            $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
            if ($piutangBulan > 0) {
                $sheet->getStyle("D{$rowNum}")->getFont()->getColor()->setRGB('D97706');
            }
            $sheet->getStyle("E{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("F{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);

            // Warna saldo: merah jika negatif
            if ($saldoBulanan < 0) {
                $sheet->getStyle("F{$rowNum}")->getFont()->getColor()->setRGB('DC2626');
            }

            // Baris zebra
            if ($idx % 2 === 0) {
                $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }

            $rowNum++;
        }

        // Baris kosong pemisah
        $rowNum++;

        // Ringkasan total
        $summaryStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
        ];
        $numFmt = '"Rp "#,##0';

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL PEMASUKAN TAHUNAN');
        $sheet->setCellValue("C{$rowNum}", $ringkasan['total_pemasukan_kotor']);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray($summaryStyle);
        $rowNum++;

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TERMASUK PEMASUKAN PIUTANG');
        $sheet->setCellValue("D{$rowNum}", $ringkasan['total_pemasukan_piutang']);
        $sheet->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("D{$rowNum}")->getFont()->getColor()->setRGB('D97706');
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'italic' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9EE']],
        ]);
        $rowNum++;

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL PENGELUARAN TAHUNAN');
        $sheet->setCellValue("E{$rowNum}", $ringkasan['total_pengeluaran_kotor']);
        $sheet->getStyle("E{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray($summaryStyle);
        $rowNum++;

        $saldoColor = $ringkasan['saldo_tersisa'] >= 0 ? 'DCFCE7' : 'FEE2E2';
        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'SALDO BERSIH');
        $sheet->setCellValue("F{$rowNum}", $ringkasan['saldo_tersisa']);
        $sheet->getStyle("F{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $saldoColor]],
        ]);
        $rowNum += 2;

        // Saldo piutang outstanding (tagihan menunggak belum lunas)
        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'SALDO PIUTANG (BELUM LUNAS)');
        $sheet->setCellValue("C{$rowNum}", $ringkasan['saldo_piutang']);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("C{$rowNum}")->getFont()->getColor()->setRGB('D97706');
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
        ]);
        $rowNum++;

        $sheet->mergeCells("A{$rowNum}:F{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'Catatan: Saldo piutang adalah total tagihan belum lunas dari seluruh pelanggan (semua periode). Bukan bagian dari arus kas yang sudah diterima.');
        $sheet->getStyle("A{$rowNum}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Auto-size semua kolom
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "laporan_arus_kas_{$year}.xls";
        $writer = new Xls($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    public function exportRiwayatPembayaran(Request $request)
    {
        $month = (int) $request->query('month', date('m'));
        $year  = (int) $request->query('year',  date('Y'));

        $data = $this->adminRepo->getMonthlyDetail($month, $year);
        $rows = $data['riwayat_transaksi'] ?? [];

        $bulanNama = ['', 'Januari','Februari','Maret','April','Mei','Juni',
                      'Juli','Agustus','September','Oktober','November','Desember'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Riwayat Pembayaran');

        // Judul
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', "Riwayat Pembayaran – {$bulanNama[$month]} {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B1B42']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Header kolom
        $headers = ['NAMA PELANGGAN', 'ALAMAT', 'TOTAL PEMBAYARAN', 'METODE', 'STATUS'];
        foreach ($headers as $col => $label) {
            $cell = chr(65 + $col) . '2';
            $sheet->setCellValue($cell, $label);
        }
        $sheet->getStyle('A2:E2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data
        $totalSemua  = 0;
        $totalTunai  = 0;
        $totalNonTunai = 0;
        $rowNum = 3;

        foreach ($rows as $item) {
            $sheet->setCellValue("A{$rowNum}", $item['nama_pelanggan']);
            $sheet->setCellValue("B{$rowNum}", $item['alamat']);
            $sheet->setCellValue("C{$rowNum}", $item['total_pembayaran']);
            $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->setCellValue("D{$rowNum}", $item['metode']);
            $sheet->setCellValue("E{$rowNum}", $item['status']);

            if ($rowNum % 2 === 0) {
                $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }

            $totalSemua += $item['total_pembayaran'];
            if ($item['metode'] === 'TUNAI') $totalTunai += $item['total_pembayaran'];
            else $totalNonTunai += $item['total_pembayaran'];

            $rowNum++;
        }

        // Baris kosong pemisah
        $rowNum++;

        // Ringkasan
        $summaryStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
        ];
        $pemasukanPiutang = (float) ($data['stats']['pemasukan_piutang'] ?? 0);
        $pembayaranBulanIni = $totalSemua - $pemasukanPiutang;

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("C{$rowNum}", $totalSemua);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($summaryStyle);
        $rowNum++;

        $subStyle = ['font' => ['color' => ['rgb' => '475569']]];
        $sheet->setCellValue("B{$rowNum}", 'Pembayaran Tagihan Bulan Ini');
        $sheet->setCellValue("C{$rowNum}", $pembayaranBulanIni);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($subStyle);
        $rowNum++;

        $sheet->setCellValue("B{$rowNum}", 'Pelunasan Piutang Bulan Lalu');
        $sheet->setCellValue("C{$rowNum}", $pemasukanPiutang);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($subStyle);
        $rowNum++;

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL TUNAI');
        $sheet->setCellValue("C{$rowNum}", $totalTunai);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($summaryStyle);
        $rowNum++;

        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL NON-TUNAI (QRIS/Midtrans)');
        $sheet->setCellValue("C{$rowNum}", $totalNonTunai);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($summaryStyle);
        $rowNum++;

        $rowNum++;
        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'SALDO PIUTANG (Belum Lunas)');
        $sheet->setCellValue("C{$rowNum}", $data['stats']['tagihan_tertunda'] ?? 0);
        $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'B91C1C']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
        ]);

        // Auto-size kolom
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "riwayat_pembayaran_{$bulanNama[$month]}_{$year}.xls";
        $writer = new Xls($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    public function exportPengaduan(Request $request)
    {
        $month = (int) $request->query('month', date('m'));
        $year  = (int) $request->query('year',  date('Y'));

        $bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni',
                      'Juli','Agustus','September','Oktober','November','Desember'];

        $rows = \App\Models\Complaint::with('user')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->latest()
            ->get();

        $summary = [
            'total'   => $rows->count(),
            'belum'   => $rows->where('status', 'belumProses')->count(),
            'proses'  => $rows->where('status', 'proses')->count(),
            'selesai' => $rows->where('status', 'selesai')->count(),
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Pengaduan');

        // Judul
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', "Laporan Pengaduan Pelanggan – {$bulanNama[$month]} {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B1B42']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Ringkasan
        $summaryStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
        ];
        $sheet->setCellValue('A2', 'Total Pengaduan'); $sheet->setCellValue('B2', $summary['total']);
        $sheet->setCellValue('C2', 'Belum Diproses');  $sheet->setCellValue('D2', $summary['belum']);
        $sheet->setCellValue('A3', 'Sedang Diproses'); $sheet->setCellValue('B3', $summary['proses']);
        $sheet->setCellValue('C3', 'Selesai');         $sheet->setCellValue('D3', $summary['selesai']);
        $sheet->getStyle('A2:E3')->applyFromArray($summaryStyle);

        // Header kolom
        $headers = ['TANGGAL', 'NAMA PELANGGAN', 'KATEGORI', 'DESKRIPSI', 'STATUS'];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue(chr(65 + $col) . '5', $label);
        }
        $sheet->getStyle('A5:E5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data
        $rowNum = 6;
        foreach ($rows as $item) {
            $statusLabel = match($item->status) {
                'belumProses' => 'BELUM DIPROSES',
                'proses'      => 'SEDANG DIPROSES',
                'selesai'     => 'SELESAI',
                default       => strtoupper($item->status),
            };
            $sheet->setCellValue("A{$rowNum}", \Carbon\Carbon::parse($item->created_at)->format('d/m/Y'));
            $sheet->setCellValue("B{$rowNum}", $item->user->namaLengkap ?? 'N/A');
            $sheet->setCellValue("C{$rowNum}", $item->kategori ?? 'Lainnya');
            $sheet->setCellValue("D{$rowNum}", $item->deskripsi);
            $sheet->setCellValue("E{$rowNum}", $statusLabel);
            if ($rowNum % 2 === 0) {
                $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }
            $rowNum++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "laporan_pengaduan_{$bulanNama[$month]}_{$year}.xls";
        $writer = new Xls($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    public function updateExpenseStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approve,reject,pending'
            ]);

            // Gunakan ExpenseRepository yang sudah kita buat sebelumnya untuk update status
            $expense = \App\Models\Expense::findOrFail($id);
            $expense->update(['status' => $request->status]);

            return $this->successResponse($expense, "Status pengeluaran berhasil diperbarui menjadi " . $request->status);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
