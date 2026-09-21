<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row settings for how letter numbers are composed.
        Schema::create('letter_numbering_settings', function (Blueprint $table) {
            $table->id();
            $table->string('number_format')->default('{prefix}-{seq}/{code}/{roman}/{year}');
            $table->string('office_code', 16)->default('KSN');
            $table->unsignedTinyInteger('sequence_digits')->default(3);
            $table->string('reset_period', 16)->default('yearly'); // yearly | monthly | never
            $table->boolean('sequence_per_prefix')->default(true);  // S and BA count separately
            $table->timestamps();
        });

        // One counter per prefix + period. Editable so the office can start from
        // wherever its paper register left off.
        Schema::create('letter_number_counters', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 8);       // '*' when sequence_per_prefix is off
            $table->string('period_key', 8);   // '2026', '2026-09', or 'all'
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'period_key']);
        });

        DB::table('letter_numbering_settings')->insert([
            'number_format'       => config('letter.number_format', '{prefix}-{seq}/{code}/{roman}/{year}'),
            'office_code'         => config('letter.number_code', 'KSN'),
            'sequence_digits'     => (int) config('letter.sequence_digits', 3),
            'reset_period'        => 'yearly',
            'sequence_per_prefix' => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_number_counters');
        Schema::dropIfExists('letter_numbering_settings');
    }
};
