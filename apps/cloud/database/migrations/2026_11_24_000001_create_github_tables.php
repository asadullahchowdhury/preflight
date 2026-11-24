<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A GitHub App installation on a user or organization account.
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('github_id')->unique();
            $table->unsignedBigInteger('account_id');
            $table->string('account_login');
            $table->string('account_type', 20);
            $table->string('account_avatar_url')->nullable();
            $table->string('repository_selection', 10)->default('selected');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        // Which users may see an installation (confirmed through the user's own GitHub token).
        Schema::create('installation_user', function (Blueprint $table) {
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['installation_id', 'user_id']);
        });

        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('full_name');
            $table->boolean('private')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('full_name');
        });

        // One run of preflight:check sent from CI.
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('pr_number')->nullable();
            $table->string('head_sha', 40);
            $table->string('base_ref')->nullable();
            $table->string('ref')->nullable();
            $table->string('actor')->nullable();
            $table->string('run_url')->nullable();
            $table->string('status', 20)->default('received');
            $table->string('severity', 10)->nullable();
            $table->string('mysql_version', 20);
            $table->boolean('size_aware')->default(false);
            $table->unsignedSmallInteger('statement_count')->default(0);
            $table->unsignedSmallInteger('danger_count')->default(0);
            $table->unsignedSmallInteger('warn_count')->default(0);
            $table->json('migrations');
            $table->json('report')->nullable();
            $table->unsignedBigInteger('check_run_id')->nullable();
            $table->unsignedBigInteger('comment_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'created_at']);
            $table->index(['repository_id', 'pr_number']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('statement_index');
            $table->string('migration')->nullable();
            $table->string('table_name')->nullable();
            $table->string('rule', 40);
            $table->string('severity', 10);
            $table->string('algorithm', 10)->nullable();
            $table->string('lock_level', 10)->nullable();
            $table->boolean('rebuilds_table')->default(false);
            $table->boolean('blocks_writes')->default(false);
            $table->text('operation');
            $table->text('message');
            $table->text('suggestion')->nullable();
            $table->timestamps();

            $table->index(['analysis_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
        Schema::dropIfExists('analyses');
        Schema::dropIfExists('repositories');
        Schema::dropIfExists('installation_user');
        Schema::dropIfExists('installations');
    }
};
