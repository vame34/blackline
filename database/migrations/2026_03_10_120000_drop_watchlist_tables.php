<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('watchlist_items')) {
            try {
                Schema::table('watchlist_items', function (Blueprint $table): void {
                    $table->dropForeign(['watchlist_id']);
                });
            } catch (\Throwable) {
                // Foreign key can be absent on some DBs or previous manual changes.
            }
        }

        Schema::dropIfExists('watchlist_items');
        Schema::dropIfExists('watchlists');
    }

    public function down(): void
    {
        Schema::create('watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('watchlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('watchlist_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 20);
            $table->timestamps();

            $table->unique(['watchlist_id', 'symbol']);
        });
    }
};
