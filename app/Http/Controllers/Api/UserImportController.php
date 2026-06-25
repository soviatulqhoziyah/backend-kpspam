<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class UserImportController extends Controller
{
    use ApiResponse;

    // Download template Excel
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['namaLengkap', 'username', 'noTelepon', 'alamat', 'role', 'meteranAwal', 'password'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([($col + 1), 1], $header);
        }

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0075D1']],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Contoh data
        $examples = [
            ['Budi Santoso', 'budi001', '081234567890', 'taltim', 'pelanggan', 120, ''],
            ['Siti Aminah', 'siti002', '082345678901', 'talbar', 'pelanggan', 250, ''],
            ['Ahmad Petugas', 'ahmad003', '083456789012', 'taltim', 'petugas', 0, 'rahasia123'],
        ];
        foreach ($examples as $row => $data) {
            foreach ($data as $col => $value) {
                $sheet->setCellValue([($col + 1), ($row + 2)], $value);
            }
        }

        // Lebar kolom
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Catatan di bawah
        $sheet->setCellValue('A6', 'Catatan:');
        $sheet->setCellValue('A7', 'alamat: isi dengan "taltim" (Talang Timur) atau "talbar" (Talang Barat)');
        $sheet->setCellValue('A8', 'role: isi dengan "pelanggan" atau "petugas"');
        $sheet->setCellValue('A9', 'meteranAwal: angka meteran saat ini (hanya untuk pelanggan, isi 0 jika tidak ada)');
        $sheet->setCellValue('A10', 'password: isi password, atau kosongkan untuk otomatis pakai username+"123"');

        $sheet->getStyle('A6:A10')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('888888'));

        $writer = new Xls($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template_import_user.xls', [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }

    // Proses import
    public function import(Request $request)
    {
        // Tangkap output stray (PHP warning/notice dari PhpSpreadsheet atau OPcache lama)
        // agar tidak merusak JSON response body
        ob_start();

        try {
            $uploadedFile = $request->file('file');
            if ($uploadedFile === null || !$uploadedFile->isValid()) {
                ob_end_clean();
                $phpErrors = [1=>'INI_SIZE',2=>'FORM_SIZE',3=>'PARTIAL',4=>'NO_FILE',6=>'NO_TMP_DIR',7=>'CANT_WRITE',8=>'EXTENSION'];
                $code = $uploadedFile?->getError() ?? 4;
                return $this->errorResponse('File gagal diproses server (kode: ' . ($phpErrors[$code] ?? $code) . '). Hubungi administrator.');
            }

            $validator = Validator::make(
                ['file' => $uploadedFile],
                ['file' => 'required|file|max:5120'],
                [
                    'file.required' => 'File harus diunggah.',
                    'file.file'     => 'File tidak valid.',
                    'file.max'      => 'Ukuran file melebihi batas (maks 5MB).',
                ]
            );
            if ($validator->fails()) {
                ob_end_clean();
                return $this->errorResponse($validator->errors()->first());
            }

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            if (count($rows) < 2) {
                return $this->errorResponse('File Excel kosong atau hanya berisi header.');
            }

            // Validasi header kolom harus sesuai template (case-insensitive)
            $templateHeaders = ['namaLengkap', 'username', 'noTelepon', 'alamat', 'role', 'meteranAwal', 'password'];
            $headerRow = array_slice($rows[0], 0, 7);
            $mismatch = [];
            foreach ($templateHeaders as $i => $expected) {
                $actual = trim((string) ($headerRow[$i] ?? ''));
                if (strtolower($actual) !== strtolower($expected)) {
                    $mismatch[] = "kolom " . ($i + 1) . " seharusnya '{$expected}', ditemukan '" . ($actual ?: '(kosong)') . "'";
                }
            }
            if (!empty($mismatch)) {
                return $this->errorResponse(
                    'Format kolom tidak sesuai template: ' . implode('; ', $mismatch) . '. Silakan gunakan template yang disediakan.'
                );
            }

            // Lewati baris header (index 0)
            $dataRows = array_slice($rows, 1);

            $berhasil = 0;
            $gagal = [];
            $rowNumber = 2;

            foreach ($dataRows as $row) {
                // Berhenti jika baris kosong (untuk menghindari catatan di bawah template)
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                    break;
                }

                [$namaLengkap, $username, $noTelepon, $alamat, $role, $meteranAwal, $password] = array_pad($row, 7, null);

                $namaLengkap  = trim((string) ($namaLengkap ?? ''));
                $username     = trim((string) ($username ?? ''));
                // Bersihkan noTelepon: strip spasi/tanda hubung, lalu pastikan numerik
                $noTeleponRaw = trim((string) ($noTelepon ?? ''));
                $noTelepon    = preg_replace('/[^0-9]/', '', $noTeleponRaw);
                $alamat       = strtolower(trim((string) ($alamat ?? '')));
                $role         = strtolower(trim((string) ($role ?? '')));
                $meteranAwal  = (int) ($meteranAwal ?? 0);
                $password     = trim((string) ($password ?? ''));

                // Auto password jika kosong
                if ($password === '') {
                    $password = $username . '123';
                }

                $validator = Validator::make(
                    compact('namaLengkap', 'username', 'noTelepon', 'alamat', 'role', 'meteranAwal'),
                    [
                        'namaLengkap' => 'required|string|min:3|max:100',
                        'username'    => 'required|string|min:3|max:50|unique:users,username',
                        'noTelepon'   => 'required|string|min:8|max:15',
                        'alamat'      => 'required|in:talbar,taltim',
                        'role'        => 'required|in:pelanggan,petugas,admin',
                        'meteranAwal' => 'integer|min:0',
                    ],
                    [
                        'namaLengkap.min'  => 'namaLengkap minimal 3 karakter',
                        'username.unique'  => "username '$username' sudah dipakai",
                        'username.min'     => 'username minimal 3 karakter',
                        'noTelepon.min'    => 'noTelepon minimal 8 digit',
                        'noTelepon.max'    => 'noTelepon maksimal 15 digit',
                        'alamat.in'        => "alamat harus 'taltim' atau 'talbar'",
                        'role.in'          => "role harus 'pelanggan', 'petugas', atau 'admin'",
                        'meteranAwal.min'  => 'meteranAwal tidak boleh negatif',
                    ]
                );

                if ($validator->fails()) {
                    $gagal[] = [
                        'baris'  => $rowNumber,
                        'nama'   => $namaLengkap ?: '(kosong)',
                        'alasan' => implode('; ', $validator->errors()->all()),
                    ];
                    $rowNumber++;
                    continue;
                }

                try {
                    User::create([
                        'namaLengkap' => $namaLengkap,
                        'username'    => $username,
                        'noTelepon'   => $noTelepon,
                        'alamat'      => $alamat,
                        'role'        => $role,
                        'status'      => 'aktif',
                        'meteranAwal' => $role === 'pelanggan' ? $meteranAwal : 0,
                        'password'    => Hash::make($password),
                    ]);
                    $berhasil++;
                } catch (Exception $e) {
                    $gagal[] = [
                        'baris'  => $rowNumber,
                        'nama'   => $namaLengkap,
                        'alasan' => 'Gagal simpan: ' . $e->getMessage(),
                    ];
                }

                $rowNumber++;
            }

            ob_end_clean(); // Buang output stray, kirim JSON bersih
            return $this->successResponse([
                'berhasil'       => $berhasil,
                'gagal'          => count($gagal),
                'detail_gagal'   => $gagal,
            ], "$berhasil user berhasil diimport" . (count($gagal) > 0 ? ", " . count($gagal) . " gagal." : "."));

        } catch (Exception $e) {
            ob_end_clean();
            return $this->errorResponse('Gagal membaca file: ' . $e->getMessage());
        }
    }
}
