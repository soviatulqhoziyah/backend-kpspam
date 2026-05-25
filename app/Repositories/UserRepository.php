<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository
{

    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function getPaginatedUsers($request)
    {
        // 1. Buat Query Dasar
        $query = $this->model->query();

        // 2. LOGIKA FILTER ROLE (Petugas / Pelanggan)
        // Jika di URL ada ?role=petugas, maka filter role-nya
        if ($request->has('role') && $request->role != 'Semua') {
            $query->where('role', $request->role);
        }

        // 3. LOGIKA CARI NAMA (Search)
        // Jika di URL ada ?search=Budi, maka cari di kolom namaLengkap atau username
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('namaLengkap', 'like', '%' . $request->search . '%')
                    ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        // 4. Hitung Summary (Statistik di atas kartu UI)
        // Kita hitung total SEBELUM di-paginate
        $summary = [
            'total_pengguna' => $this->model->count(),
            'petugas_aktif' => $this->model->where('role', 'petugas')->where('status', 'aktif')->count(),
            'pelanggan_aktif' => $this->model->where('role', 'pelanggan')->where('status', 'aktif')->count(),
        ];

        // 5. Eksekusi Pagination (10 data per halaman)
        $users = $query->latest()->paginate(10);

        // 6. Mapping format ID agar cantik di UI (PLG-001 atau USR-001)
        $users->getCollection()->transform(function ($user) {
            $prefix = ($user->role === 'pelanggan') ? 'PLG-' : 'USR-';
            return [
                'id' => $user->id,
                'id_display' => $prefix . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'namaLengkap' => $user->namaLengkap,
                'username' => $user->username,
                'role' => $user->role,
                'noTelepon' => $user->noTelepon,
                'alamat' => $user->alamat,
                'status' => strtoupper($user->status),
            ];
        });

        return [
            'summary' => $summary,
            'list' => $users
        ];
    }
    public function storeUser($data)
    {
        $data['password'] = Hash::make($data['password']);
        return $this->model->create($data);
    }

    public function updateUser($id, $data)
    {
        $user = $this->model->findOrFail($id);
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        return $user;
    }

    public function deleteUser($id)
    {
        return $this->model->findOrFail($id)->delete();
    }
}
