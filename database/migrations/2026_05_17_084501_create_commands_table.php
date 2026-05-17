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
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('static'); // static | dynamic
            $table->string('handler')->nullable(); // dynamic handler key
            $table->text('response')->nullable(); // static template
            $table->string('permission')->default('everyone'); // everyone|subscriber|moderator|broadcaster
            $table->unsignedInteger('cooldown_seconds')->default(5);
            $table->unsignedInteger('user_cooldown_seconds')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('reply_in_thread')->default(false);
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
