<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->string('source_kind', 40)->nullable()->after('leap_day_policy');
            $table->string('source_title', 160)->nullable()->after('source_kind');
            $table->string('source_reference', 160)->nullable()->after('source_title');
            $table->string('source_revision', 80)->nullable()->after('source_reference');
            $table->string('source_section', 120)->nullable()->after('source_revision');
            $table->date('source_published_on')->nullable()->after('source_section');
            $table->string('source_url', 2048)->nullable()->after('source_published_on');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn([
                'source_kind',
                'source_title',
                'source_reference',
                'source_revision',
                'source_section',
                'source_published_on',
                'source_url',
            ]);
        });
    }
};
