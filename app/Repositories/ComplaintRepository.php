<?php

namespace App\Repositories;
use App\Services\SupabaseStorage;
use Illuminate\Support\Facades\Auth;
use App\Models\Complaint;

class ComplaintRepository {

    protected $model;

    public function __construct(Complaint $model) {
        $this->model = $model;
    }

    // Ambil semua pengaduan (untuk Admin)
    public function getAll() {
        return $this->model->with('user')->latest()->get();
    }

    // Ambil pengaduan milik saya sendiri (untuk Pelanggan)
    public function getByUserId($userId) {
        return $this->model->where('user_id', $userId)->latest()->get();
    }

    // Simpan pengaduan baru
    public function store($data, string $base64Image, string $ext) {
        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            throw new \Exception("Data foto tidak valid.");
        }
        $filename = 'bukti_pengaduan_' . time() . '_' . Auth::id() . '.' . $ext;
        $fotoUrl = SupabaseStorage::upload('bukti_pengaduan/' . $filename, $imageData, $ext);

        return $this->model->create([
            'user_id'   => Auth::id(),
            'deskripsi' => $data['deskripsi'],
            'kategori'  => $data['kategori'] ?? null,
            'fotoBukti' => $fotoUrl,
            'status'    => 'belumProses'
        ]);
    }

    // Update status pengaduan (oleh Admin)
    public function updateStatus($id, $status) {
        $complaint = $this->model->findOrFail($id);
        $complaint->update(['status' => $status]);
        return $complaint;
    }
}