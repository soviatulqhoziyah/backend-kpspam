<?php


use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PelangganController;
use App\Http\Controllers\Api\PetugasDashboardController;
use App\Http\Controllers\Api\TarifController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
// Endpoint ini akan dipanggil otomatis oleh server Midtrans
Route::post('/midtrans/callback', [MidtransWebhookController::class, 'handleNotification']);


// Harus Login
Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return [
            'status' => 'success',
            'message' => 'Data profil login berhasil diambil',
            'data' => $request->user() // Mengambil data "SAYA"
        ];
    });

    // Pengaduan
    Route::middleware('role:admin')->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::put('/complaints/{id}/status', [ComplaintController::class, 'updateStatus']); // Khusus Admin 
    });

    // Endpoint khusus dashboard petugas
    Route::prefix('petugas')->middleware('role:petugas')->group(function () {
        Route::get('/dashboard', [PetugasDashboardController::class, 'index']); //beranda
        Route::get('/customers', [PetugasDashboardController::class, 'getCustomers']); //daftar pelanggan
        Route::get('/customers/{id}', [PetugasDashboardController::class, 'showCustomer']); //detail pelanggan
        Route::post('/billing-input', [BillingController::class, 'store']); //input tagihan
        Route::post('/payments', [PaymentController::class, 'store']); //proses pembayaran
        Route::post('/payments/sync', [PaymentController::class, 'syncMidtrans']); //sinkronisasi pembayaran non tunai
        Route::get('/expenses', [ExpenseController::class, 'index']); //pengeluaran
        Route::post('/expenses', [ExpenseController::class, 'store']); //catat pengeluaran
        Route::put('/expenses/{id}/status', [ExpenseController::class, 'updateStatus']); // Khusus Admin
        Route::get('/tarif-aktif', [TarifController::class, 'getActiveTarif']); //tarif aktif untuk billing input
        Route::get('/profile', [PetugasDashboardController::class, 'profile']); //profil
    });

    // Endpoint khusus pelanggan
    Route::prefix('pelanggan')->middleware('role:pelanggan')->group(function () {
        Route::get('/dashboard', [PelangganController::class, 'index']);
        Route::get('/history', [PelangganController::class, 'history']);
        Route::get('/complaints', [PelangganController::class, 'myComplaints']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/profile', [PelangganController::class, 'profile']);
        Route::post('/payments/checkout', [PaymentController::class, 'checkout']);
        Route::post('/payments/sync', [PaymentController::class, 'syncMidtrans']);
    });

    // Dashboard Admin (Web)
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard-summary', [AdminDashboardController::class, 'index']);
        Route::get('/dashboard-summary/export', [AdminDashboardController::class, 'exportRingkasanTahunan']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::get('/users/import-template', [UserImportController::class, 'downloadTemplate']);
        Route::post('/users/import', [UserImportController::class, 'import']);
        Route::get('/transaction-detail', [AdminDashboardController::class, 'monthlyDetail']);
        Route::get('/transaction-detail/export', [AdminDashboardController::class, 'exportRiwayatPembayaran']);
        Route::post('/confirm-setoran/{petugasId}', [AdminDashboardController::class, 'confirmPayment']);
        Route::get('/complaints-management', [AdminDashboardController::class, 'complaintIndex']);
        Route::get('/complaints-management/export', [AdminDashboardController::class, 'exportPengaduan']);
        Route::get('/tarifs', [TarifController::class, 'index']);
        Route::post('/tarifs', [TarifController::class, 'store']);
        Route::put('/tarifs/{id}', [TarifController::class, 'update']);
        Route::delete('/tarifs/{id}', [TarifController::class, 'destroy']);
        Route::get('/expenses-audit', [AdminDashboardController::class, 'expenseAudit']);
        Route::put('/expenses/{id}/status', [AdminDashboardController::class, 'updateExpenseStatus']);
    });
});
