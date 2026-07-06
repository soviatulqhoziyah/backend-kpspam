<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function index(Request $request)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->unauthorizedResponse("Hanya admin yang dapat mengelola data user.");
            }

            $data = $this->userRepo->getPaginatedUsers($request);
            return $this->successResponse($data, "Daftar user berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            $validated = $request->validated();
            $user = $this->userRepo->storeUser($validated);
            return $this->createdResponse($user, "User berhasil dibuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(UserRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $user = $this->userRepo->updateUser($id, $validated);
            return $this->successResponse($user, "User berhasil diperbarui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    public function destroy($id)
    {
        try {
            $this->userRepo->deleteUser($id);
            return $this->successResponse(null, "User berhasil dihapus");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getPendingUsers()
    {
        try {
            $data = $this->userRepo->getPendingUsers();
            return $this->successResponse($data, "Daftar verifikasi berhasil dimuat");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            $this->userRepo->approveUser($id);
            return $this->successResponse(null, "Pendaftaran berhasil disetujui");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $request->validate(['catatan' => 'nullable|string|max:500']);
            $this->userRepo->rejectUser($id, $request->catatan);
            return $this->successResponse(null, "Pendaftaran berhasil ditolak");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function tagihPiutang(Request $request, $id)
    {
        try {
            $request->validate(['metode' => 'required|in:tunai,transfer']);

            $pendingUser = User::whereIn('status', ['pending', 'ditolak'])->findOrFail($id);

            $oldUserIds = User::where('no_kk', $pendingUser->no_kk)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['pending', 'ditolak'])
                ->pluck('id');

            if ($oldUserIds->isEmpty()) {
                return $this->errorResponse('Tidak ada piutang ditemukan untuk No. KK ini.', 404);
            }

            $billings = Billing::whereIn('user_id', $oldUserIds)
                ->where('status', 'menunggak')
                ->get();

            if ($billings->isEmpty()) {
                return $this->errorResponse('Tidak ada piutang yang perlu dibayar.', 404);
            }

            $total = $billings->sum('totalTagihan');

            if ($request->metode === 'tunai') {
                return $this->successResponse([
                    'metode'         => 'tunai',
                    'total_piutang'  => (float) $total,
                    'jumlah_tagihan' => $billings->count(),
                ], 'Menunggu konfirmasi pembayaran tunai.');
            }

            // Transfer — generate Midtrans Snap token
            \Midtrans\Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
            \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_IS_PRODUCTION', false);
            \Midtrans\Config::$isSanitized  = true;
            \Midtrans\Config::$is3ds        = true;

            $orderId = 'PIUTANG-' . time() . '-' . $id;

            foreach ($billings as $bill) {
                $bill->update(['midtrans_order_id' => $orderId]);
            }

            $snapToken = \Midtrans\Snap::getSnapToken([
                'transaction_details' => [
                    'order_id'     => $orderId,
                    'gross_amount' => (int) $total,
                ],
                'customer_details' => [
                    'first_name' => $pendingUser->namaLengkap,
                    'phone'      => $pendingUser->noTelepon,
                ],
            ]);

            $baseUrl = (bool) env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://app.midtrans.com/snap/v2/vtweb/'
                : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/';

            return $this->successResponse([
                'metode'        => 'transfer',
                'redirect_url'  => $baseUrl . $snapToken,
                'snap_token'    => $snapToken,
                'order_id'      => $orderId,
                'total_piutang' => (float) $total,
            ], 'Link pembayaran piutang berhasil dibuat.');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function konfirmasiTunaiPiutang($id)
    {
        try {
            $pendingUser = User::whereIn('status', ['pending', 'ditolak'])->findOrFail($id);

            $oldUserIds = User::where('no_kk', $pendingUser->no_kk)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['pending', 'ditolak'])
                ->pluck('id');

            if ($oldUserIds->isEmpty()) {
                return $this->errorResponse('Tidak ada piutang ditemukan.', 404);
            }

            DB::transaction(function () use ($oldUserIds) {
                $billings = Billing::whereIn('user_id', $oldUserIds)
                    ->where('status', 'menunggak')
                    ->get();

                foreach ($billings as $bill) {
                    $bill->update(['status' => 'lunas']);
                    Payment::create([
                        'billing_id'         => $bill->id,
                        'user_id'            => $bill->user_id,
                        'nominalPembayaran'  => $bill->totalTagihan,
                        'metodePembayaran'   => 'tunai',
                        'tanggalBayar'       => now(),
                    ]);
                }
            });

            return $this->successResponse(null, 'Piutang lunas. Pendaftaran dapat disetujui.');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
