<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signal_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('strategy_key', 64)->default('volume_v1')->index();
            $table->json('strategy_params')->nullable();

            $table->string('exchange', 32)->default('binance');
            $table->string('symbol', 32)->index();
            $table->string('timeframe', 16)->index();
            $table->string('direction', 8)->index();

            $table->timestamp('entry_at')->nullable()->index();
            $table->decimal('entry_price', 20, 8);
            $table->decimal('stop_loss', 20, 8);
            $table->decimal('take_profit', 20, 8);
            $table->decimal('reward_risk', 8, 2)->nullable();

            $table->string('status', 16)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 32)->nullable();
            $table->decimal('close_price', 20, 8)->nullable();

            $table->text('notes')->nullable();
            $table->json('debug')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['exchange', 'symbol', 'timeframe']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
