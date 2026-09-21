<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who will sign each slot, chosen while drafting: {"1": userId, "2": userId}.
        Schema::table('letters', function (Blueprint $table) {
            $table->json('signers')->nullable()->after('values');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn('signers');
        });
    }
};
