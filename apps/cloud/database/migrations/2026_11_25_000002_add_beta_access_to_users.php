<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Private beta: people join a waitlist when they first sign in, and an admin approves them.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('github_token');
            $table->string('team_size', 20)->nullable()->after('approved_at');
            $table->text('waitlist_note')->nullable()->after('team_size');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'team_size', 'waitlist_note']);
        });
    }
};
