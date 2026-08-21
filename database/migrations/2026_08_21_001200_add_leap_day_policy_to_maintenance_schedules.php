<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->string('leap_day_policy', 24)->nullable()->after('annual_day');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn('leap_day_policy');
        });
    }
};
