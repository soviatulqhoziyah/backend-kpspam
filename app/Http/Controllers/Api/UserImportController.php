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

// Stream wrapper agar PhpSpreadsheet bisa baca dari memory tanpa filesystem
class KpspamMemStream {
    private static array $store = [];
    private string $key = '';
    private int $pos = 0;
    public $context;

    public static function put(string $data): string {
        $key = uniqid('k', true);
        self::$store[$key] = $data;
        if (!in_array('kpspamss', stream_get_wrappers())) {
            stream_wrapper_register('kpspamss', self::class);
        }
        return 'kpspamss://' . $key . '/import.xls';
    }

    public static function free(string $uri): void {
        $key = parse_url($uri, PHP_URL_HOST);
        unset(self::$store[$key]);
    }

    public function stream_open(string $path, string $mode, int $opts, ?string &$opened): bool {
        $this->key = parse_url($path, PHP_URL_HOST);
        $this->pos = 0;
        return isset(self::$store[$this->key]);
    }

    public function stream_read(int $count): string {
        $chunk = substr(self::$store[$this->key] ?? '', $this->pos, $count);
        $this->pos += strlen($chunk);
        return $chunk;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool {
        $len = strlen(self::$store[$this->key] ?? '');
        $this->pos = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->pos + $offset,
            SEEK_END => $len + $offset,
            default  => $this->pos,
        };
        return $this->pos >= 0 && $this->pos <= $len;
    }

    public function stream_tell(): int { return $this->pos; }
    public function stream_eof(): bool { return $this->pos >= strlen(self::$store[$this->key] ?? ''); }
    public function stream_close(): void {}
    public function stream_write(string $d): int { return 0; }

    private function statArray(int $size): array {
        $s = ['dev'=>0,'ino'=>0,'mode'=>0100644,'nlink'=>1,'uid'=>0,'gid'=>0,
              'rdev'=>0,'size'=>$size,'atime'=>0,'mtime'=>0,'ctime'=>0,'blksize'=>-1,'blocks'=>-1];
        return array_merge(array_values($s), $s);
    }

    public function stream_stat(): array {
        return $this->statArray(strlen(self::$store[$this->key] ?? ''));
    }

    public function url_stat(string $path, int $flags): array {
        $key = parse_url($path, PHP_URL_HOST);
        return $this->statArray(strlen(self::$store[$key] ?? ''));
    }
}

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
        ob_start();

        try {
            $base64 = $request->input('file_base64');
            $ext    = $request->input('file_ext', 'xlsx');

            if (empty($base64)) {
                ob_end_clean();
                return $this->errorResponse('File harus diunggah.');
            }

            $fileData = base64_decode($base64, true);
            if ($fileData === false || strlen($fileData) === 0) {
                ob_end_clean();
                return $this->errorResponse('File tidak valid atau rusak.');
            }

            // Baca dari memory via custom stream wrapper — zero filesystem write
            $readerType = strtolower($ext) === 'xlsx' ? 'Xlsx' : 'Xls';
            $streamUri = KpspamMemStream::put($fileData);
            $reader = IOFactory::createReader($readerType);
            $spreadsheet = $reader->load($streamUri);
            KpspamMemStream::free($streamUri);
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

            ob_end_clean();
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
