<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reminder_occurrences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('maintenance_schedule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('reference_type', 20);
            $table->string('reference_value', 20);
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['maintenance_schedule_id', 'reference_type', 'reference_value'],
                'maintenance_reminder_occurrence_reference_unique',
            );
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reminder_occurrences');
    }
};
