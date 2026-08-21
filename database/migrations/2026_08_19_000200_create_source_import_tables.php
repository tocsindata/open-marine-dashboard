<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_source_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key', 64);
            $table->text('encrypted_token');
            $table->string('token_hint', 12)->nullable();
            $table->string('label', 100)->nullable();
            $table->decimal('default_latitude', 10, 7)->nullable();
            $table->decimal('default_longitude', 11, 7)->nullable();
            $table->unsignedSmallInteger('interval_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 24)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider_key']);
        });

        Schema::create('source_import_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider_key', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 100)->default('shared');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('interval_minutes')->default(15);
            $table->timestamp('next_due_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->string('last_result', 24)->nullable();
            $table->text('last_message')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
            $table->unique(['provider_key', 'scope_key']);
        });

        Schema::create('source_import_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_import_step_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 24)->index();
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_written')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('source_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider_key', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('external_id', 190);
            $table->string('record_type', 80)->index();
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamp('source_issued_at')->nullable()->index();
            $table->timestamp('source_expires_at')->nullable()->index();
            $table->timestamp('retrieved_at')->index();
            $table->timestamps();
            $table->unique(['provider_key', 'user_id', 'external_id']);
        });

        DB::table('source_import_steps')->insert([
            [
                'id' => (string) str()->ulid(),
                'provider_key' => 'eccc_alerts',
                'scope_key' => 'shared',
                'enabled' => true,
                'interval_minutes' => 5,
                'next_due_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) str()->ulid(),
                'provider_key' => 'chs_stations',
                'scope_key' => 'shared',
                'enabled' => true,
                'interval_minutes' => 1440,
                'next_due_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('source_records');
        Schema::dropIfExists('source_import_runs');
        Schema::dropIfExists('source_import_steps');
        Schema::dropIfExists('user_source_credentials');
    }
};
