<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); 
            $table->string('namaPengeluaran', 100);
            $table->decimal('nominal', 10, 2);
            $table->string('fotoBukti')->nullable();
            $table->timestamp('tanggalPengeluaran');
            $table->enum('status', ['approve', 'reject', 'pending'])->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
