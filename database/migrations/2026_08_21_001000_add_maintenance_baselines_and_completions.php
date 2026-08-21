<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_schedules', 'baseline_on')) {
            Schema::table('maintenance_schedules', function (Blueprint $table): void {
                $table->date('baseline_on')->nullable();
            });
        }

        if (! Schema::hasColumn('maintenance_schedules', 'baseline_hours')) {
            Schema::table('maintenance_schedules', function (Blueprint $table): void {
                $table->decimal('baseline_hours', 10, 1)->nullable();
            });
        }

        if (! Schema::hasTable('maintenance_completions')) {
            Schema::create('maintenance_completions', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('maintenance_schedule_id')->constrained()->cascadeOnDelete();
                $table->date('completed_on');
                $table->decimal('meter_hours', 10, 1)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['maintenance_schedule_id', 'completed_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_completions');

        foreach (['baseline_on', 'baseline_hours'] as $column) {
            if (Schema::hasColumn('maintenance_schedules', $column)) {
                Schema::table('maintenance_schedules', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
