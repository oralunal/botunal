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
        Schema::create('kick_bans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('target_kick_user_id')->nullable()->index();
            $table->string('target_username')->index();
            $table->string('moderator_username')->nullable();
            $table->string('action'); // ban | timeout | unban
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = permanent
            $table->string('source')->default('webhook'); // webhook | dashboard
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_bans');
    }
};
