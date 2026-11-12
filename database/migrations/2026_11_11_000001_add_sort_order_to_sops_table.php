<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Fixed SOP order for the Projects filter rail, set by admins on the SOP page.
     * Seeded from the order the rail shows today (most projects first), so
     * nothing moves until someone reorders.
     */
    public function up(): void
    {
        Schema::table('sops', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name')->index();
        });

        DB::table('sops')
            ->leftJoin('projects', 'projects.sop_id', '=', 'sops.id')
            ->groupBy('sops.id', 'sops.name')
            ->orderByRaw('COUNT(projects.id) DESC')
            ->orderBy('sops.name')
            ->pluck('sops.id')
            ->each(fn ($id, $index) => DB::table('sops')->where('id', $id)->update(['sort_order' => $index + 1]));
    }

    public function down(): void
    {
        Schema::table('sops', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
