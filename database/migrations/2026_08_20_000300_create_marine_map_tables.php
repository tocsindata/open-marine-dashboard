<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_positions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vessel_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->decimal('speed_knots', 8, 2)->nullable();
            $table->decimal('course_degrees', 6, 2)->nullable();
            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            $table->index(['vessel_id', 'recorded_at']);
        });

        DB::table('source_import_steps')->updateOrInsert(
            ['provider_key' => 'usgs_earthquakes', 'scope_key' => 'shared'],
            [
                'id' => (string) str()->ulid(),
                'enabled' => true,
                'interval_minutes' => 5,
                'next_due_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('source_import_steps')->where('provider_key', 'usgs_earthquakes')->delete();
        Schema::dropIfExists('vessel_positions');
    }
};
