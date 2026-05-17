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
        Schema::create('kick_follows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follower_kick_user_id')->nullable()->index();
            $table->string('follower_username')->index();
            $table->timestamp('followed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_follows');
    }
};
