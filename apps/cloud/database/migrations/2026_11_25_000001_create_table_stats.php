<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `php artisan preflight:stats --push` authenticates with a per-repository token; only its hash is stored.
        Schema::table('repositories', function (Blueprint $table) {
            $table->string('stats_token_hash', 64)->nullable()->unique()->after('settings');
            $table->timestamp('stats_token_created_at')->nullable()->after('stats_token_hash');
            $table->timestamp('stats_pushed_at')->nullable()->after('stats_token_created_at');
            $table->date('stats_captured_on')->nullable()->after('stats_pushed_at');
            $table->string('stats_server_version', 40)->nullable()->after('stats_captured_on');
        });

        // One row per table per day: the latest day is what analyses use, earlier days show growth.
        Schema::create('table_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->date('captured_on');
            $table->unsignedBigInteger('rows');
            $table->unsignedBigInteger('data_bytes');
            $table->unsignedBigInteger('index_bytes');
            $table->timestamps();

            $table->unique(['repository_id', 'table_name', 'captured_on']);
            $table->index(['repository_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_stats');
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropUnique(['stats_token_hash']);
            $table->dropColumn(['stats_token_hash', 'stats_token_created_at', 'stats_pushed_at', 'stats_captured_on', 'stats_server_version']);
        });
    }
};
