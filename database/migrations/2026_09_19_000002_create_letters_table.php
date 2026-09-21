<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Number is only assigned when the letter is submitted, so drafts never burn a sequence.
            $table->string('number')->nullable()->unique();
            $table->unsignedInteger('sequence')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('letter_date')->nullable();

            $table->string('subject');
            $table->json('values');                         // field values keyed by field key
            $table->longText('rendered_html')->nullable();  // frozen body at submit time
            $table->string('status', 24)->default('draft'); // draft | submitted | signed | rejected | canceled
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
