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
        Schema::create('command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('command_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alias_used')->nullable();
            $table->string('invoker_username')->index();
            $table->unsignedBigInteger('invoker_kick_user_id')->nullable();
            $table->text('raw_message');
            $table->text('response_sent')->nullable();
            $table->string('outcome'); // sent|cooldown|denied|error|disabled
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_logs');
    }
};
