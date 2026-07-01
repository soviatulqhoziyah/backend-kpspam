<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\ComplaintRequest;
use App\Repositories\ComplaintRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;


class ComplaintController extends Controller {
    use ApiResponse;

    protected ComplaintRepository $complaintRepo;

    public function __construct(ComplaintRepository $complaintRepo) {
        $this->complaintRepo = $complaintRepo;
    }

    // List pengaduan (Admin lihat semua, Pelanggan lihat miliknya saja)
    public function index() {
        try {
            $user = Auth::user();
            if ($user->role === 'admin') {
                $data = $this->complaintRepo->getAll();
            } else {
                $data = $this->complaintRepo->getByUserId($user->id);
            }
            return $this->successResponse($data, "Data pengaduan berhasil diambil");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // Pelanggan buat pengaduan
    public function store(ComplaintRequest $request) {
        try {
            $validated = $request->validated();
            $base64Image = $request->input('foto_bukti_base64');
            $ext = $request->input('foto_bukti_ext', 'jpg');

            $complaint = $this->complaintRepo->store($validated, $base64Image, $ext);
            return $this->createdResponse($complaint, "Pengaduan berhasil terkirim");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // Admin update status (Proses/Selesai)
    public function updateStatus(Request $request, $id) {
        try {
            $request->validate(['status' => 'required|in:belumProses,proses,selesai']);
            
            $complaint = $this->complaintRepo->updateStatus($id, $request->status);
            return $this->successResponse($complaint, "Status pengaduan diperbarui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}