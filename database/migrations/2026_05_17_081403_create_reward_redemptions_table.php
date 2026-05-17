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
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('kick_redemption_id')->unique();
            $table->string('reward_title')->nullable();
            $table->unsignedInteger('reward_cost')->nullable();
            $table->unsignedBigInteger('redeemer_kick_user_id')->nullable()->index();
            $table->string('redeemer_username')->index();
            $table->text('user_input')->nullable();
            $table->string('status')->nullable()->index(); // pending | fulfilled | canceled
            $table->timestamp('redeemed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
