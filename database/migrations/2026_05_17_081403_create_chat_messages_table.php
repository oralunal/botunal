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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('kick_message_id')->unique();
            $table->unsignedBigInteger('sender_kick_user_id')->nullable()->index();
            $table->string('sender_username')->index();
            $table->json('sender_identity')->nullable();
            $table->text('content');
            $table->boolean('is_command')->default(false)->index();
            $table->string('replied_to_message_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();

            $table->index(['sender_username', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
