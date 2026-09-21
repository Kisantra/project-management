<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_requested_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 64);              // which checklist field on the template this belongs to
            $table->string('name');
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_received')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['letter_id', 'field_key', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requested_documents');
    }
};
