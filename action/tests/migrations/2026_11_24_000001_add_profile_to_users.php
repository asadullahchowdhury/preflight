<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('email');
            $table->text('bio')->nullable();
            $table->string('name', 500)->change();
            $table->fullText('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropFullText(['bio']);
            $table->dropColumn(['timezone', 'bio']);
            $table->string('name')->change();
        });
    }
};
