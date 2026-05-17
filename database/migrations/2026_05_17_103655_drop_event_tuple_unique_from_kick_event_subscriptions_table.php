<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the (event_name, event_version, broadcaster_user_id) composite
     * unique. Kick can legitimately have several subscriptions for the same
     * event, and reconcile keys on kick_subscription_id (which stays unique),
     * so this composite constraint only caused 1062 errors during sync.
     */
    public function up(): void
    {
        Schema::table('kick_event_subscriptions', function (Blueprint $table) {
            $table->dropUnique('kick_event_sub_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kick_event_subscriptions', function (Blueprint $table) {
            $table->unique(['event_name', 'event_version', 'broadcaster_user_id'], 'kick_event_sub_unique');
        });
    }
};
