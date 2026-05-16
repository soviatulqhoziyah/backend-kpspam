<?php

namespace App\Repositories;
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
    public function store($data, $file) {
        $path = $file->store('bukti_pengaduan', 'public');

        return $this->model->create([
            'user_id'   => Auth::id(), // Otomatis ambil ID yang sedang login
            'deskripsi' => $data['deskripsi'],
            'fotoBukti' => $path,
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