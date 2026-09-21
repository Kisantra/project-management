<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master list of documents the office commonly asks clients for.
        Schema::create('requested_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 48)->default('lainnya'); // keuangan | perpajakan | legal | kepegawaian | lainnya
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A checklist item may point at a master entry; free-text items keep it null.
        Schema::table('letter_requested_documents', function (Blueprint $table) {
            $table->foreignId('requested_document_type_id')
                ->nullable()
                ->after('field_key')
                ->constrained('requested_document_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('letter_requested_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_document_type_id');
        });

        Schema::dropIfExists('requested_document_types');
    }
};
