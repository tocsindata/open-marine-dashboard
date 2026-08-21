<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may commit CREATE TABLE before a later ALTER TABLE constraint fails.
        // An unrecorded table at this point is an empty partial result of this migration.
        Schema::dropIfExists('maintenance_completion_reversals');

        Schema::create('maintenance_completion_reversals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('maintenance_completion_id');
            $table->text('reason');
            $table->timestamps();

            $table->foreign('maintenance_completion_id', 'mcr_completion_fk')
                ->references('id')
                ->on('maintenance_completions')
                ->cascadeOnDelete();

            // One appended reversal preserves the original completion and prevents conflicting correction states.
            $table->unique('maintenance_completion_id', 'mcr_completion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_completion_reversals');
    }
};
