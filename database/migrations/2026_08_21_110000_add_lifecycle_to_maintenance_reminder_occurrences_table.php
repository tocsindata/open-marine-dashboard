<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_reminder_occurrences', function (Blueprint $table): void {
            $table->timestamp('suppressed_at')->nullable()->after('acknowledged_at');
            $table->timestamp('dismissed_at')->nullable()->after('suppressed_at');
            $table->timestamp('superseded_at')->nullable()->after('dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_reminder_occurrences', function (Blueprint $table): void {
            $table->dropColumn(['suppressed_at', 'dismissed_at', 'superseded_at']);
        });
    }
};
