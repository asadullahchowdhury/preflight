<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('reason', 20)->default('other');
            $table->timestamps();
        });

        DB::table('refunds')->where('reason', 'other')->update(['reason' => 'unknown']);
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
