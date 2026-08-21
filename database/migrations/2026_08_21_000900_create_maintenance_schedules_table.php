<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vessel_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('equipment_item_id')->nullable()->constrained('vessel_equipment_items')->nullOnDelete();
            $table->string('name', 120);
            $table->string('basis', 24)->index();
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->decimal('interval_hours', 10, 1)->nullable();
            $table->unsignedTinyInteger('annual_month')->nullable();
            $table->unsignedTinyInteger('annual_day')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vessel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
