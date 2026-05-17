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
        Schema::create('kick_gifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_kick_user_id')->nullable()->index();
            $table->string('sender_username')->index();
            $table->string('recipient_username')->nullable();
            $table->string('gift_name')->nullable();
            $table->unsignedInteger('kicks_amount')->default(0)->index();
            $table->text('message')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_gifts');
    }
};
