<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_equipment_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vessel_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24)->index();
            $table->string('name', 120);
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vessel_id', 'status']);
            $table->index(['vessel_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_equipment_items');
    }
};
