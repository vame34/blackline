<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicates = DB::table('signal_filter_presets')
            ->select('user_id', 'name', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id', 'name')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('signal_filter_presets')
                ->where('user_id', $duplicate->user_id)
                ->where('name', $duplicate->name)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id');

            $keepId = $ids->first();
            if ($keepId === null) {
                continue;
            }

            DB::table('signal_filter_presets')
                ->where('user_id', $duplicate->user_id)
                ->where('name', $duplicate->name)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('signal_filter_presets', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signal_filter_presets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
        });
    }
};
