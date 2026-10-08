<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('remember_token');
            $table->timestamp('personal_data_consent_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('age_confirmed_at')->nullable()->after('personal_data_consent_at');
            $table->timestamp('marketing_consent_at')->nullable()->after('age_confirmed_at');
            $table->string('consent_ip', 45)->nullable()->after('marketing_consent_at');
            $table->text('consent_user_agent')->nullable()->after('consent_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_accepted_at',
                'personal_data_consent_at',
                'age_confirmed_at',
                'marketing_consent_at',
                'consent_ip',
                'consent_user_agent',
            ]);
        });
    }
};
