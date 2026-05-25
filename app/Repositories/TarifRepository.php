<?php

namespace App\Repositories;

use App\Models\Tarif;
use Illuminate\Support\Facades\DB;

class TarifRepository {

    protected $model;

    public function __construct(Tarif $model) {
        $this->model = $model;
    }

    public function getAll() {
        return $this->model->latest()->get();
    }

    public function store($data) {
        return DB::transaction(function () use ($data) {
            // Jika tarif baru ini diset AKTIF
            if ($data['status'] === 'aktif') {
                // Nonaktifkan semua tarif lain & set tanggal_selesai untuk yang aktif sebelumnya
                $this->model->where('status', 'aktif')->update([
                    'status' => 'tidak aktif',
                    'tanggal_selesai' => now()
                ]);
                $data['tanggal_mulai'] = now();
            }

            return $this->model->create($data);
        });
    }

    public function update($id, $data) {
        return DB::transaction(function () use ($id, $data) {
            $tarif = $this->model->findOrFail($id);

            // Jika status diubah dari tidak aktif menjadi AKTIF
            if ($data['status'] === 'aktif' && $tarif->status !== 'aktif') {
                $this->model->where('status', 'aktif')->update([
                    'status' => 'tidak aktif',
                    'tanggal_selesai' => now()
                ]);
                $data['tanggal_mulai'] = now();
                $data['tanggal_selesai'] = null;
            }

            $tarif->update($data);
            return $tarif;
        });
    }

    public function delete($id) {
        $tarif = $this->model->findOrFail($id);
        if ($tarif->status === 'aktif') {
            throw new \Exception("Tarif yang sedang aktif tidak boleh dihapus.");
        }
        return $tarif->delete();
    }
}