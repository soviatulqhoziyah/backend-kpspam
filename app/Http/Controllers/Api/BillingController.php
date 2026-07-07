<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingRequest;
use App\Repositories\BillingRepository;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    protected $billingRepo;

    public function __construct(BillingRepository $billingRepo)
    {
        $this->billingRepo = $billingRepo;
    }

    public function showBulanIni($userId)
    {
        try {
            $billing = $this->billingRepo->getBillingBulanIni((int) $userId);
            if (!$billing) {
                return $this->errorResponse('Tagihan bulan ini belum dicatat.');
            }
            return $this->successResponse([
                'id'               => $billing->id,
                'periode'          => $billing->periode,
                'meteran_lalu'     => $billing->meteranLalu,
                'meteran_sekarang' => $billing->meteranSekarang,
                'jumlah_pemakaian' => $billing->jumlahPemakaian,
                'total_tagihan'    => (float) $billing->totalTagihan,
                'foto_meteran'     => $billing->fotoMeteran,
                'status'           => $billing->status,
                'can_edit'         => $billing->status === 'menunggak',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(Request $request, $billingId)
    {
        try {
            $request->validate([
                'meteranSekarang'     => 'required|integer|min:0',
                'foto_meteran_base64' => 'nullable|string',
                'foto_meteran_ext'    => 'nullable|string|in:jpg,jpeg,png',
            ]);
            $billing = $this->billingRepo->updateBilling(
                (int) $billingId,
                (int) $request->meteranSekarang,
                $request->input('foto_meteran_base64'),
                $request->input('foto_meteran_ext', 'jpg'),
            );
            return $this->successResponse([
                'id'               => $billing->id,
                'meteran_sekarang' => $billing->meteranSekarang,
                'jumlah_pemakaian' => $billing->jumlahPemakaian,
                'total_tagihan'    => (float) $billing->totalTagihan,
                'foto_meteran'     => $billing->fotoMeteran,
            ], 'Tagihan berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(BillingRequest $request)
    {
        try {
            // 1. Ambil data yang sudah lolos validasi
            $validated = $request->validated();

            // 2. Ambil base64 foto dari JSON body
            $base64Image = $request->input('foto_meteran_base64');
            $ext = $request->input('foto_meteran_ext', 'jpg');

            // 3. Panggil Repository
            $billing = $this->billingRepo->storeBilling($validated, $base64Image, $ext);

            // 4. Ambil tagihan menunggak SELAIN yang baru dibuat (hindari duplikat)
            $unpaidBillings = \App\Models\Billing::where('user_id', $validated['user_id'])
                ->where('status', 'menunggak')
                ->where('id', '!=', $billing->id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn($b) => [
                    'id'               => $b->id,
                    'periode'          => $b->periode,
                    'total_tagihan'    => (float) $b->totalTagihan,
                    'status'           => $b->status,
                ]);

            return $this->createdResponse([
                'new_billing' => [
                    'id'               => $billing->id,
                    'periode'          => $billing->periode,
                    'meteran_lalu'     => $billing->meteranLalu,
                    'meteran_sekarang' => $billing->meteranSekarang,
                    'jumlah_pemakaian' => $billing->jumlahPemakaian,
                    'total_tagihan'    => (float) $billing->totalTagihan,
                    'foto_meteran'     => $billing->fotoMeteran,
                    'status'           => $billing->status,
                ],
                'unpaid_billings' => $unpaidBillings,
            ], "Tagihan berhasil diterbitkan");
        } catch (\Exception $e) {
            // Billing sudah ada bulan ini — kembalikan data billing yang ada
            // agar petugas tetap bisa lanjut ke halaman pembayaran
            if (str_contains($e->getMessage(), 'sudah diterbitkan')) {
                $existingBilling = \App\Models\Billing::where('user_id', $validated['user_id'])
                    ->where('periode', $validated['periode'])
                    ->first();

                if ($existingBilling) {
                    $unpaidBillings = \App\Models\Billing::where('user_id', $validated['user_id'])
                        ->where('status', 'menunggak')
                        ->where('id', '!=', $existingBilling->id)
                        ->orderBy('id', 'asc')
                        ->get()
                        ->map(fn($b) => [
                            'id'            => $b->id,
                            'periode'       => $b->periode,
                            'total_tagihan' => (float) $b->totalTagihan,
                            'status'        => $b->status,
                        ]);

                    return $this->successResponse([
                        'new_billing' => [
                            'id'               => $existingBilling->id,
                            'periode'          => $existingBilling->periode,
                            'meteran_lalu'     => $existingBilling->meteranLalu,
                            'meteran_sekarang' => $existingBilling->meteranSekarang,
                            'jumlah_pemakaian' => $existingBilling->jumlahPemakaian,
                            'total_tagihan'    => (float) $existingBilling->totalTagihan,
                            'foto_meteran'     => $existingBilling->fotoMeteran,
                            'status'           => $existingBilling->status,
                        ],
                        'unpaid_billings' => $unpaidBillings,
                        'already_exists'  => true,
                    ], 'Tagihan periode ini sudah ada, lanjutkan ke pembayaran.');
                }
            }

            return $this->errorResponse($e->getMessage());
        }
    }
}
