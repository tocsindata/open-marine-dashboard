<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_equipment_items', function (Blueprint $table): void {
            $table->date('installed_on')->nullable();
            $table->date('last_serviced_on')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->decimal('meter_hours', 10, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vessel_equipment_items', function (Blueprint $table): void {
            $table->dropColumn([
                'installed_on',
                'last_serviced_on',
                'warranty_expires_on',
                'meter_hours',
            ]);
        });
    }
};
