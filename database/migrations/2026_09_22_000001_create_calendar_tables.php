<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Team calendar for project management: appointments, data-request
        // reminders and other custom events, optionally tied to a client/project.
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('kind', 30)->default('appointment');   // appointment | data_request | reminder | other
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();                // room, address or meeting link
            $table->text('description')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('scheduled');    // scheduled | done | canceled
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
        });

        Schema::create('calendar_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['calendar_event_id', 'user_id']);
        });

        // One row per reminder offset; sent_at is stamped by the scheduled command.
        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes_before');
            $table->dateTime('remind_at');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_reminders');
        Schema::dropIfExists('calendar_event_participants');
        Schema::dropIfExists('calendar_events');
    }
};
