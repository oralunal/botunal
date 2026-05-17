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
        Schema::create('kick_event_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('kick_subscription_id')->nullable()->unique();
            $table->string('event_name');
            $table->unsignedTinyInteger('event_version')->default(1);
            $table->string('method')->default('webhook');
            $table->unsignedBigInteger('broadcaster_user_id')->nullable();
            $table->string('status')->default('active'); // active | failed | deleted
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['event_name', 'event_version', 'broadcaster_user_id'], 'kick_event_sub_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_event_subscriptions');
    }
};
