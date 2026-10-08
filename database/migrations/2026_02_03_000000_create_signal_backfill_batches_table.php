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
        Schema::create('signal_backfill_batches', function (Blueprint $table) {
            $table->id();
            $table->string('exchange', 32)->index();
            $table->string('provider', 32)->index();
            $table->string('interval', 16);
            $table->string('context_interval', 16);
            $table->unsignedInteger('limit')->default(0);
            $table->unsignedInteger('context_limit')->default(0);
            $table->unsignedBigInteger('start_time_ms')->nullable();
            $table->unsignedBigInteger('end_time_ms')->nullable();
            $table->json('symbols');
            $table->json('fallbacks')->nullable();
            $table->string('category_slug', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('publish')->default(false);
            $table->boolean('simulate_exits')->default(false);
            $table->boolean('dry_run')->default(false);
            $table->boolean('broadcast')->default(false);
            $table->unsignedInteger('total_signals')->default(0);
            $table->unsignedInteger('total_created')->default(0);
            $table->json('results')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_backfill_batches');
    }
};
