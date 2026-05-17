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
        Schema::create('kick_users', function (Blueprint $table) {
            $table->id();
            // Nullable + unique: legacy events may carry no Kick user id; the
            // database (MySQL and SQLite) permits multiple NULLs in a unique
            // index, so null-id users are keyed by username instead.
            $table->unsignedBigInteger('kick_user_id')->nullable()->unique();
            $table->string('username')->index();
            $table->json('identity')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kick_users');
    }
};
