<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $locations = ['vancouver', 'victoria', 'tofino', 'prince_rupert'];
        foreach (['open_meteo_weather' => 30, 'open_meteo_marine' => 60] as $provider => $interval) {
            foreach ($locations as $location) {
                DB::table('source_import_steps')->updateOrInsert(
                    ['provider_key' => $provider, 'scope_key' => $location],
                    [
                        'id' => (string) str()->ulid(),
                        'enabled' => true,
                        'interval_minutes' => $interval,
                        'next_due_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('source_import_steps')
            ->whereIn('provider_key', ['open_meteo_weather', 'open_meteo_marine'])
            ->delete();
    }
};
