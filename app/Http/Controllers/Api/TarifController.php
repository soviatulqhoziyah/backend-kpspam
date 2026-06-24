<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TarifRequest;
use App\Repositories\TarifRepository;
use App\Traits\ApiResponse;
use Exception;

class TarifController extends Controller {
    use ApiResponse;

    protected $tarifRepo;

    public function __construct(TarifRepository $tarifRepo) {
        $this->tarifRepo = $tarifRepo;
    }

    public function index() {
        try {
            $data = $this->tarifRepo->getAll();
            return $this->successResponse($data, "Data tarif berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(TarifRequest $request) {
        try {
            $tarif = $this->tarifRepo->store($request->validated());
            return $this->createdResponse($tarif, "Tarif baru berhasil ditambahkan");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(TarifRequest $request, $id) {
        try {
            $tarif = $this->tarifRepo->update($id, $request->validated());
            return $this->successResponse($tarif, "Tarif berhasil diperbarui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getActiveTarif() {
        try {
            $tarif = $this->tarifRepo->getActive();
            if (!$tarif) {
                return $this->errorResponse("Tarif aktif tidak ditemukan", 404);
            }
            return $this->successResponse($tarif, "Tarif aktif berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function destroy($id) {
        try {
            $this->tarifRepo->delete($id);
            return $this->successResponse(null, "Tarif berhasil dihapus");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}