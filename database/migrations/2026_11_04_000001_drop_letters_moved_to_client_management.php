<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The letters module (Penyuratan) moved to the digital-marketing app, which
 * is now the office's single register for outgoing letters. Its tables, the
 * user signature column and the surat.* permission go with it.
 *
 * `users.job_title` stays: the active-users widget and the staff forms show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('letter_requested_documents');
        Schema::dropIfExists('letter_signatures');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_number_counters');
        Schema::dropIfExists('letter_numbering_settings');
        Schema::dropIfExists('requested_document_types');
        Schema::dropIfExists('letter_templates');

        if (Schema::hasColumn('users', 'signature_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('signature_path');
            });
        }

        // Role links cascade from the permission row.
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('name', 'surat.*')->delete();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Recreates the empty tables as they stood before the move. Templates,
     * document types and the permission are not reseeded: the seeders went
     * with the module.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('avatar_path');
        });

        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 32)->default('surat');
            $table->string('number_prefix', 8)->default('S');
            $table->string('icon')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->json('signers');
            $table->json('fields');
            $table->longText('body');
            $table->string('closing')->nullable();
            $table->boolean('checklist_as_attachment')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('requested_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 48)->default('lainnya');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('letter_numbering_settings', function (Blueprint $table) {
            $table->id();
            $table->string('number_format')->default('{prefix}-{seq}/{code}/{roman}/{year}');
            $table->string('office_code', 16)->default('KSN');
            $table->unsignedTinyInteger('sequence_digits')->default(3);
            $table->string('reset_period', 16)->default('yearly');
            $table->boolean('sequence_per_prefix')->default(true);
            $table->timestamps();
        });

        Schema::create('letter_number_counters', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 8);
            $table->string('period_key', 8);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'period_key']);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('number')->nullable()->unique();
            $table->unsignedInteger('sequence')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('letter_date')->nullable();
            $table->string('subject');
            $table->json('values');
            $table->json('signers')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('letter_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order');
            $table->string('label');
            $table->json('roles');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->string('verification_code', 16)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['letter_id', 'order']);
        });

        Schema::create('letter_requested_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->foreignId('requested_document_type_id')->nullable()->constrained('requested_document_types')->nullOnDelete();
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
};
