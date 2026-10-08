<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->string('trusted_ip', 45)->nullable()->after('last_used_ip');
            $table->index('trusted_ip');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropIndex(['trusted_ip']);
            $table->dropColumn('trusted_ip');
        });
    }
};

