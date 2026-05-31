<?php

namespace App\Repositories;

use App\Models\Billing;
use App\Models\Tarif;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class BillingRepository
{
    protected $model;

    public function __construct(Billing $model)
    {
        $this->model = $model;
    }

    public function storeBilling(array $data, $file)
    {
        // Gunakan Transaction agar jika ada error di tengah jalan, database tetap bersih
        return DB::transaction(function () use ($data, $file) {

            // 1. Ambil Tarif Aktif
            $tarif = Tarif::where('status', 'aktif')->first();
            if (!$tarif) {
                throw new Exception("Tarif aktif tidak ditemukan. Harap hubungi admin.");
            }

            // 2. Konversi Periode ke Objek Carbon (Memastikan format benar)
            try {
                $englishMonths = [
                    'Januari' => 'January', 'Februari' => 'February', 'Maret' => 'March',
                    'Mei' => 'May', 'Juni' => 'June', 'Juli' => 'July',
                    'Agustus' => 'August', 'Oktober' => 'October', 'Desember' => 'December'
                ];
                $englishPeriode = strtr($data['periode'], $englishMonths);
                $inputDate = Carbon::parse($englishPeriode);
            } catch (Exception $e) {
                throw new Exception("Format periode tidak valid. Gunakan format 'Bulan Tahun' (Contoh: Mei 2026).");
            }
            $now = Carbon::now();

            // LOGIKA KETAT: Cek apakah Bulan & Tahun yang diinput sama dengan Bulan & Tahun saat ini
            if ($inputDate->format('m-Y') !== $now->format('m-Y')) {
                Carbon::setLocale('id');
                throw new Exception("Gagal: Anda hanya diperbolehkan menginput tagihan untuk bulan " . $now->translatedFormat('F Y'));
            }

            // 3. LOGIKA: Tidak boleh input untuk bulan depan (Future Date)
            // Kita bandingkan bulan & tahunnya saja
            // 3. LOGIKA: Cek Duplikasi (Agar tidak double input di bulan yang sama)
            $isAlreadyExists = $this->model->where('user_id', $data['user_id'])
                ->where('periode', $data['periode'])
                ->exists();

            if ($isAlreadyExists) {
                throw new Exception("Tagihan pelanggan ini untuk periode '{$data['periode']}' sudah diterbitkan.");
            }

            // 4. LOGIKA: Cek Duplikasi (Satu user hanya boleh punya satu tagihan per bulan)
            // Menggunakan format yang seragam untuk pengecekan
            $isAlreadyExists = $this->model->where('user_id', $data['user_id'])
                ->where('periode', $data['periode'])
                ->exists();
            if ($isAlreadyExists) {
                throw new Exception("Tagihan pelanggan ini untuk periode '{$data['periode']}' sudah pernah diterbitkan.");
            }

            // 5. Cari Meteran Terakhir (History bulan sebelumnya)
            $lastBilling = $this->model->where('user_id', $data['user_id'])
                ->orderBy('id', 'desc')
                ->first();

            $meteranLalu = $lastBilling ? $lastBilling->meteranSekarang : 0;

            // 6. LOGIKA: Validasi angka meteran
            $meteranSekarang = $data['meteranSekarang'];
            if ($meteranSekarang < $meteranLalu) {
                throw new Exception("Error: Angka meteran sekarang ($meteranSekarang) lebih kecil dari bulan lalu ($meteranLalu).");
            }

            // 7. Hitung Pemakaian & Total Tagihan
            $jumlahPemakaian = $meteranSekarang - $meteranLalu;
            $totalTagihan = ($jumlahPemakaian * $tarif->hargaPerM) + $tarif->biayaBeban;

            // 8. Proses Upload Foto (Simpan path ke variabel)
            try {
                $path = $file->store('bukti_meteran', 'public');
            } catch (Exception $e) {
                throw new Exception("Gagal mengunggah foto. Silakan coba lagi.");
            }

            // 9. Simpan ke Database
            return $this->model->create([
                'user_id'         => $data['user_id'],
                'tarif_id'        => $tarif->id,
                'periode'         => $data['periode'],
                'meteranLalu'     => $meteranLalu,
                'meteranSekarang' => $meteranSekarang,
                'jumlahPemakaian' => $jumlahPemakaian,
                'fotoMeteran'     => $path,
                'totalTagihan'    => $totalTagihan,
                'status'          => 'menunggak'
            ]);
        });
    }
}
