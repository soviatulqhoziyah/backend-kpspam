<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tarif_id')->constrained('tarifs');
            $table->string('periode', 20); 
            $table->integer('meteranLalu');
            $table->integer('meteranSekarang');
            $table->integer('jumlahPemakaian');
            $table->string('fotoMeteran')->nullable();
            $table->decimal('totalTagihan', 10, 2);
            $table->enum('status', ['lunas', 'menunggak'])->default('menunggak');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
