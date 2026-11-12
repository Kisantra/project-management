<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Project statuses get one of the design-system tones (blue, neutral, red,
     * outline) instead of a free colour. `color` stays for the dashboard
     * charts until they move to tones too.
     */
    public function up(): void
    {
        Schema::table('project_statuses', function (Blueprint $table) {
            $table->string('tone', 10)->default('neutral')->after('color');
        });

        // Same mapping as App\Support\Ui\Tone::forProjectStatus() before this column existed.
        DB::table('project_statuses')->where('category', 'active')->whereIn('shape', ['clock', 'dashed'])->update(['tone' => 'outline']);
        DB::table('project_statuses')->where('category', 'active')->whereNotIn('shape', ['clock', 'dashed'])->update(['tone' => 'blue']);
        DB::table('project_statuses')->where('category', 'done')->update(['tone' => 'blue']);
    }

    public function down(): void
    {
        Schema::table('project_statuses', function (Blueprint $table) {
            $table->dropColumn('tone');
        });
    }
};
