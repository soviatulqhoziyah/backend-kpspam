<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PetugasDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

// Harus Login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/billing/input', [BillingController::class, 'store']);

    // Pengaduan
    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::post('/complaints', [ComplaintController::class, 'store']);
    Route::put('/complaints/{id}/status', [ComplaintController::class, 'updateStatus']); // Khusus Admin

    // Pengeluaran
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{id}/status', [ExpenseController::class, 'updateStatus']); // Khusus Admin

    //Payment
    Route::post('/payments', [PaymentController::class, 'store']);

    // Endpoint khusus dashboard petugas
    Route::get('/petugas/dashboard', [PetugasDashboardController::class, 'index']);
});