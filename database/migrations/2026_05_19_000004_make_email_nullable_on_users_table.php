<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make users.email nullable so members who log in via Kick without an
     * email are created with no email and forced to complete their profile.
     * The existing unique index is preserved; nullable + unique coexist and
     * multiple NULL emails are permitted by SQLite and MariaDB.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Restore the NOT NULL constraint on users.email.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
