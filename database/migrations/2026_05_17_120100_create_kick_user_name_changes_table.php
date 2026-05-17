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
        Schema::create('kick_user_name_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kick_user_id')->index();
            $table->string('previous_username');
            $table->string('new_username');
            $table->timestamp('changed_at')->index();
            $table->timestamps();

            // Lets the backfill rebuild rename history idempotently.
            $table->unique(
                ['kick_user_id', 'previous_username', 'new_username'],
                'kick_user_name_changes_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_user_name_changes');
    }
};
