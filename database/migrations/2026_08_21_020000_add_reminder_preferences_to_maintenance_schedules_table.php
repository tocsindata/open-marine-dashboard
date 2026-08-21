<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->boolean('reminder_enabled')->default(false)->after('source_url');
            $table->unsignedSmallInteger('reminder_lead_days')->nullable()->after('reminder_enabled');
            $table->decimal('reminder_lead_hours', 10, 1)->nullable()->after('reminder_lead_days');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn([
                'reminder_enabled',
                'reminder_lead_days',
                'reminder_lead_hours',
            ]);
        });
    }
};
