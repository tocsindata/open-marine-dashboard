<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_import_steps', function (Blueprint $table): void {
            $table->timestamp('consent_granted_at')->nullable();
            $table->timestamp('consent_revoked_at')->nullable();
        });

        DB::table('source_import_steps')
            ->whereNotNull('user_id')
            ->whereIn('provider_key', ['open_meteo_weather', 'open_meteo_marine'])
            ->update([
                'enabled' => false,
                'locked_at' => null,
                'consent_revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('source_import_steps', function (Blueprint $table): void {
            $table->dropColumn(['consent_granted_at', 'consent_revoked_at']);
        });
    }
};
