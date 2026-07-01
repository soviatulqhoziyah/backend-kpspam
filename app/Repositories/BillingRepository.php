<?php

namespace App\Repositories;

use App\Models\Billing;
use App\Models\Tarif;
use App\Services\SupabaseStorage;
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

    public function getBillingBulanIni(int $userId): ?Billing
    {
        $now = Carbon::now();
        return $this->model
            ->where('user_id', $userId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->first();
    }

    public function updateBilling(int $billingId, int $meteranSekarang, ?string $base64Image, ?string $ext): Billing
    {
        $billing = $this->model->findOrFail($billingId);

        if ($billing->status !== 'menunggak') {
            throw new Exception('Tagihan ini sudah lunas, tidak dapat diedit.');
        }

        $tarif = Tarif::where('status', 'aktif')->firstOrFail();
        $meteranLalu = $billing->meteranLalu;

        if ($meteranSekarang < $meteranLalu) {
            throw new Exception("Angka meteran ($meteranSekarang) lebih kecil dari bulan lalu ($meteranLalu).");
        }

        $jumlahPemakaian = $meteranSekarang - $meteranLalu;
        $totalTagihan = ($jumlahPemakaian * $tarif->hargaPerM) + $tarif->biayaBeban;

        $updateData = [
            'meteranSekarang' => $meteranSekarang,
            'jumlahPemakaian' => $jumlahPemakaian,
            'totalTagihan'    => $totalTagihan,
        ];

        if ($base64Image) {
            $imageData = base64_decode($base64Image);
            if ($imageData === false) throw new Exception('Data foto tidak valid.');
            $filename = 'bukti_meteran_' . time() . '_' . $billing->user_id . '.' . $ext;
            $updateData['fotoMeteran'] = SupabaseStorage::upload('bukti_meteran/' . $filename, $imageData, $ext);
        }

        $billing->update($updateData);
        return $billing->fresh();
    }

    public function storeBilling(array $data, string $base64Image, string $ext)
    {
        // Gunakan Transaction agar jika ada error di tengah jalan, database tetap bersih
        return DB::transaction(function () use ($data, $base64Image, $ext) {

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

            // 3. LOGIKA: Cek Duplikasi (Satu user hanya boleh punya satu tagihan per bulan)
            $isAlreadyExists = $this->model->where('user_id', $data['user_id'])
                ->where('periode', $data['periode'])
                ->exists();

            if ($isAlreadyExists) {
                throw new Exception("Tagihan pelanggan ini untuk periode '{$data['periode']}' sudah diterbitkan.");
            }

            // 5. Cari Meteran Terakhir (History bulan sebelumnya)
            $lastBilling = $this->model->where('user_id', $data['user_id'])
                ->orderBy('id', 'desc')
                ->first();

            if ($lastBilling) {
                $meteranLalu = $lastBilling->meteranSekarang;
            } else {
                // Belum ada billing sebelumnya — gunakan meteranAwal dari data user
                $meteranLalu = (int) (\App\Models\User::find($data['user_id'])->meteranAwal ?? 0);
            }

            // 6. LOGIKA: Validasi angka meteran
            $meteranSekarang = $data['meteranSekarang'];
            if ($meteranSekarang < $meteranLalu) {
                throw new Exception("Error: Angka meteran sekarang ($meteranSekarang) lebih kecil dari bulan lalu ($meteranLalu).");
            }

            // 7. Hitung Pemakaian & Total Tagihan
            $jumlahPemakaian = $meteranSekarang - $meteranLalu;
            $totalTagihan = ($jumlahPemakaian * $tarif->hargaPerM) + $tarif->biayaBeban;

            // 8. Decode base64 dan upload ke Supabase
            $imageData = base64_decode($base64Image);
            if ($imageData === false) {
                throw new Exception("Data foto tidak valid.");
            }
            $filename = 'bukti_meteran_' . time() . '_' . $data['user_id'] . '.' . $ext;
            $fotoUrl = SupabaseStorage::upload('bukti_meteran/' . $filename, $imageData, $ext);

            // 9. Simpan ke Database
            return $this->model->create([
                'user_id'         => $data['user_id'],
                'tarif_id'        => $tarif->id,
                'periode'         => $data['periode'],
                'meteranLalu'     => $meteranLalu,
                'meteranSekarang' => $meteranSekarang,
                'jumlahPemakaian' => $jumlahPemakaian,
                'fotoMeteran'     => $fotoUrl,
                'totalTagihan'    => $totalTagihan,
                'status'          => 'menunggak'
            ]);
        });
    }
}
