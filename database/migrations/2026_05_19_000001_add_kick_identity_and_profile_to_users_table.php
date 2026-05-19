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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('kick_user_id')->nullable()->unique()->after('id');
            $table->string('kick_username')->nullable()->after('kick_user_id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('last_name');
            $table->string('instagram')->nullable()->after('phone');
            $table->string('twitter')->nullable()->after('instagram');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['kick_user_id']);
            $table->dropColumn([
                'kick_user_id',
                'kick_username',
                'first_name',
                'last_name',
                'phone',
                'instagram',
                'twitter',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
