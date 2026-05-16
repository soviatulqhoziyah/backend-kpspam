<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

class ExpenseRepository {

    protected $model;

    public function __construct(Expense $model) {
        $this->model = $model;
    }

    public function getAll() {
        return $this->model->with('user')->latest()->get();
    }

    public function store($data, $file) {
        $path = $file->store('bukti_pengeluaran', 'public');

        return $this->model->create([
            'user_id' => Auth::id(),
            'namaPengeluaran' => $data['namaPengeluaran'],
            'nominal' => $data['nominal'],
            'fotoBukti' => $path,
            'tanggalPengeluaran' => now(),
            'status' => 'pending' 
        ]);
    }

    public function updateStatus($id, $status) {
        $expense = $this->model->findOrFail($id);
        $expense->update(['status' => $status]);
        return $expense;
    }
}