<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // KLU = Klasifikasi Lapangan Usaha. Kode klasifikasi bidang usaha
            // (dari NPWP/DJP), relevan untuk klien Badan. Nullable karena klien
            // Pribadi/Pemerintah umumnya tidak mengisinya.
            $table->string('klu')->nullable()->after('NPWP');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('klu');
        });
    }
};
