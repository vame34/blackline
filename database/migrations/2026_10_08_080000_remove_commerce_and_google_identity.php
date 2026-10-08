<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['alert_events', 'alert_preferences', 'web_push_subscriptions', 'tbank_payments', 'payments', 'user_subscriptions', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['google_id', 'google_avatar', 'telegram_chat_id', 'telegram_username', 'web_push_enabled'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // Removed modules are not recreated; restore the private DB backup to reverse this migration.
    }
};
