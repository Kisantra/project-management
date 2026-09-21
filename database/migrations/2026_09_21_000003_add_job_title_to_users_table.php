<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Full title printed under the name on letters (e.g. "Tax Manager"); `position`
        // stays the coarse level (Manager / Director) used for filtering.
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 100)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
