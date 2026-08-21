<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('source_import_steps')->updateOrInsert(
            ['provider_key' => 'eccc_marine_bulletins', 'scope_key' => 'pacific'],
            [
                'id' => (string) str()->ulid(),
                'enabled' => true,
                'interval_minutes' => 30,
                'next_due_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('source_import_steps')->where('provider_key', 'eccc_marine_bulletins')->delete();
    }
};
