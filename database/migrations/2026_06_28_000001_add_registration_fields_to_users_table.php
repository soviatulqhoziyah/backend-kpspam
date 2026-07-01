<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_kk', 16)->nullable()->after('namaLengkap');
            $table->string('foto_kk')->nullable()->after('no_kk');
            $table->text('catatan_penolakan')->nullable()->after('foto_kk');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('aktif', 'non_aktif', 'pending', 'ditolak') DEFAULT 'aktif'");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_kk', 'foto_kk', 'catatan_penolakan']);
        });
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('aktif', 'non_aktif') DEFAULT 'aktif'");
    }
};
