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
        Schema::create('timers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('message');
            $table->unsignedInteger('interval_seconds')->default(600);
            $table->unsignedInteger('min_messages_between')->default(0);
            $table->boolean('only_when_live')->default(true);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timers');
    }
};
