<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order');           // signing order, 1-based
            $table->string('label');                        // "Manajer", "Direktur"
            $table->json('roles');                          // roles allowed to sign this slot
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending'); // pending | signed | rejected
            $table->timestamp('signed_at')->nullable();
            $table->string('verification_code', 16)->nullable();
            $table->text('note')->nullable();               // rejection reason
            $table->timestamps();

            $table->unique(['letter_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_signatures');
    }
};
