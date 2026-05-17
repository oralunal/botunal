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
        Schema::create('livestream_events', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index(); // status.updated | metadata.updated
            $table->boolean('is_live')->nullable();
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('viewer_count')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestream_events');
    }
};
