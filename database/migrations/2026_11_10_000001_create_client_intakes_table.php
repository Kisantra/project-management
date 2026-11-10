<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Klien masuk dari Client Management (CM): satu baris per lead CM yang
     * mencapai Deal. Klien dan proyek baru dibuat setelah staf mengecek data
     * di halaman "Klien Masuk", jadi client_id/project_id kosong sampai saat itu.
     */
    public function up(): void
    {
        Schema::create('client_intakes', function (Blueprint $table) {
            $table->id();

            // Kunci idempotensi: CM boleh mengirim ulang lead yang sama.
            $table->unsignedBigInteger('cm_lead_id')->unique();

            // Ringkasan dari payload CM, disalin ke kolom supaya bisa dicari/diurutkan.
            $table->string('name');
            $table->string('entity', 50)->nullable()->comment('PT | CV | UD | Koperasi | Perorangan');
            $table->string('service')->nullable()->comment('Layanan yang di-deal di CM');
            $table->unsignedBigInteger('deal_value')->nullable()->comment('Nilai deal (rupiah)');
            $table->string('marketing_owner')->nullable()->comment('PJ marketing di CM');
            $table->date('deal_at')->nullable();
            $table->string('lead_url', 2048)->nullable();
            $table->json('payload')->nullable()->comment('Payload terakhir dari CM');

            // Saran pencocokan dengan klien yang sudah ada (NPWP atau email).
            $table->foreignId('suggested_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('suggested_by', 10)->nullable()->comment('npwp | email');

            // Diisi saat staf memproses: klien yang dibuat/ditautkan dan proyeknya.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('matched')->default(false)->comment('true = ditautkan ke klien lama');

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdrawn_reason', 20)->nullable()->comment('mundur | ditutup');
            $table->string('withdrawn_detail')->nullable();
            $table->text('withdrawn_note')->nullable();

            // Status panggilan balik PM -> CM setelah diproses.
            $table->timestamp('handoff_sent_at')->nullable();
            $table->text('handoff_error')->nullable();

            $table->timestamps();

            $table->index(['completed_at', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_intakes');
    }
};
