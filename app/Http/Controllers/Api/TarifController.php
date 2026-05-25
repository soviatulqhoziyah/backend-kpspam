<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TarifRequest;
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
            return $this->successResponse($tarif, "Tarif baru berhasil ditambahkan", 201);
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

    public function destroy($id) {
        try {
            $this->tarifRepo->delete($id);
            return $this->successResponse(null, "Tarif berhasil dihapus");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}