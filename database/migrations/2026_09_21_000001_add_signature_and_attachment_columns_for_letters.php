<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Handwritten signature image, printed on letters the user signs.
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('avatar_path');
        });

        // Render checklist fields as a "LAMPIRAN" table page instead of inline.
        Schema::table('letter_templates', function (Blueprint $table) {
            $table->boolean('checklist_as_attachment')->default(false)->after('closing');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });

        Schema::table('letter_templates', function (Blueprint $table) {
            $table->dropColumn('checklist_as_attachment');
        });
    }
};
