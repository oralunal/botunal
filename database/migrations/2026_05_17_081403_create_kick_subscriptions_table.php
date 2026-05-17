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
        Schema::create('kick_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // new | renewal | gift
            $table->unsignedBigInteger('subscriber_kick_user_id')->nullable()->index();
            $table->string('subscriber_username')->nullable()->index();
            $table->string('gifter_username')->nullable();
            $table->string('tier')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_subscriptions');
    }
};
