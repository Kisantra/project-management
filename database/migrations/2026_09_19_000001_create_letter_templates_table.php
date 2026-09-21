<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // stable key, e.g. surat-tanggapan-sp2dk
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 32)->default('surat'); // surat | berita_acara | pengingat | pemberitahuan
            $table->string('number_prefix', 8)->default('S');  // S-001/KSN/IX/2026 or BA-001/...
            $table->string('icon')->nullable();               // heroicon name for the library card
            $table->boolean('is_critical')->default(false);   // needs extra care (Direktur must sign)
            $table->json('signers');                           // [{label, roles: []}, ...] in signing order
            $table->json('fields');                            // [{key, label, type, required, placeholder, help, options, default}]
            $table->longText('body');                          // paragraphs with {{placeholders}}
            $table->string('closing')->nullable();             // closing sentence, optional
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
