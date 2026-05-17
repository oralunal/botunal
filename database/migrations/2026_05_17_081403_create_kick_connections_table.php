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
        Schema::create('kick_connections', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // channel | bot
            $table->unsignedBigInteger('kick_user_id')->nullable()->index();
            $table->string('slug')->nullable();
            $table->string('display_name')->nullable();
            $table->unsignedBigInteger('broadcaster_user_id')->nullable()->index();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->json('scopes')->nullable();
            $table->string('token_type')->default('Bearer');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_connections');
    }
};
