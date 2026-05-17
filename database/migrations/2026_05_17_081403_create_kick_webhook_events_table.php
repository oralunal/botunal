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
        Schema::create('kick_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 40)->unique();
            $table->string('event_type')->index();
            $table->unsignedTinyInteger('event_version')->default(1);
            $table->timestamp('kick_timestamp')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('process_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_webhook_events');
    }
};
