<?php

namespace App\Repositories;

use App\Models\Billing;
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
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // 3. LOGIKA CARI NAMA (Search)
        // Jika di URL ada ?search=Budi, maka cari di kolom namaLengkap atau username
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('namaLengkap', 'like', '%' . $request->search . '%')
                    ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        // Exclude pending/ditolak dari daftar utama
        $query->whereNotIn('status', ['pending', 'ditolak']);

        // 4. Hitung Summary
        $summary = [
            'total_pengguna' => $this->model->whereNotIn('status', ['pending', 'ditolak'])->count(),
            'aktif'          => $this->model->where('status', 'aktif')->count(),
            'non_aktif'      => $this->model->where('status', 'non_aktif')->count(),
            'pending'        => $this->model->where('status', 'pending')->count(),
        ];

        // 5. Eksekusi Pagination (10 data per halaman)
        $users = $query->latest()->paginate(10);

        // 6. Mapping format ID agar cantik di UI (PLG-001 atau USR-001)
        $users->getCollection()->transform(function ($user) {
            $prefix = ($user->role === 'pelanggan') ? 'PLG-' : 'USR-';
            return [
                'id'          => $user->id,
                'id_display'  => $prefix . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'namaLengkap' => $user->namaLengkap,
                'username'    => $user->username,
                'role'        => $user->role,
                'noTelepon'   => $user->noTelepon,
                'alamat'      => $user->alamat,
                'status'      => strtoupper($user->status),
                'meteranAwal' => (int) ($user->meteranAwal ?? 0),
                'no_kk'       => $user->no_kk,
                'foto_kk'     => $user->foto_kk,
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

    public function getPendingUsers()
    {
        return $this->model->whereIn('status', ['pending', 'ditolak'])
            ->where('role', 'pelanggan')
            ->latest()
            ->get()
            ->map(function ($user) {
                $piutangInfo = null;
                if ($user->no_kk) {
                    $oldUserIds = $this->model
                        ->where('no_kk', $user->no_kk)
                        ->where('id', '!=', $user->id)
                        ->whereNotIn('status', ['pending', 'ditolak'])
                        ->pluck('id');

                    if ($oldUserIds->isNotEmpty()) {
                        $totalPiutang = Billing::whereIn('user_id', $oldUserIds)
                            ->where('status', 'menunggak')
                            ->sum('totalTagihan');
                        $bulanTunggak = Billing::whereIn('user_id', $oldUserIds)
                            ->where('status', 'menunggak')
                            ->count();

                        if ($totalPiutang > 0) {
                            $piutangInfo = [
                                'total'        => (float) $totalPiutang,
                                'bulan_tunggak' => $bulanTunggak,
                            ];
                        }
                    }
                }

                return [
                    'id'                 => $user->id,
                    'id_display'         => 'PLG-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'namaLengkap'        => $user->namaLengkap,
                    'username'           => $user->username,
                    'noTelepon'          => $user->noTelepon,
                    'alamat'             => $user->alamat,
                    'no_kk'              => $user->no_kk,
                    'foto_kk'            => $user->foto_kk,
                    'status'             => $user->status,
                    'catatan_penolakan'  => $user->catatan_penolakan,
                    'created_at'         => $user->created_at?->format('d M Y, H:i'),
                    'piutang_info'       => $piutangInfo,
                ];
            });
    }

    public function approveUser($id)
    {
        $user = $this->model->findOrFail($id);
        $user->update(['status' => 'aktif', 'catatan_penolakan' => null]);
        return $user;
    }

    public function rejectUser($id, $catatan)
    {
        $user = $this->model->findOrFail($id);
        $user->update(['status' => 'ditolak', 'catatan_penolakan' => $catatan]);
        return $user;
    }
}
