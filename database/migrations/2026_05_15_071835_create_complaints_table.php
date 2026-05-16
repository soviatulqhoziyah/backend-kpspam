<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->text('deskripsi');
            $table->string('fotoBukti', 255)->nullable();
            $table->enum('status', ['belumProses', 'proses', 'selesai'])->default('belumProses');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
