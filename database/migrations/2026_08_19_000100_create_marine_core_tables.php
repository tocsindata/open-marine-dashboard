<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: database/migrations/2026_08_19_000100_create_marine_core_tables.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('user')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('timezone', 64)->default('America/Vancouver');
            $table->string('unit_system', 16)->default('metric');
            $table->string('policy_version', 32)->nullable();
            $table->timestamp('policy_accepted_at')->nullable();
        });

        Schema::create('vessels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 64)->nullable();
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('home_port', 120)->nullable();
            $table->decimal('length_m', 8, 2)->nullable();
            $table->decimal('beam_m', 8, 2)->nullable();
            $table->decimal('draft_m', 8, 2)->nullable();
            $table->string('registration_number', 80)->nullable();
            $table->string('mmsi', 16)->nullable();
            $table->string('call_sign', 32)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('vessel_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('vessel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24);
            $table->json('capabilities')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['vessel_id', 'user_id']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'due_at']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24);
            $table->string('action', 100)->index();
            $table->string('target_type', 120)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('result', 24)->default('success');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('source_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->string('approval_status', 24)->default('pending')->index();
            $table->boolean('enabled')->default(false);
            $table->string('documentation_url', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_providers');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('vessel_memberships');
        Schema::dropIfExists('vessels');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active', 'timezone', 'unit_system', 'policy_version', 'policy_accepted_at']);
        });
    }
};
