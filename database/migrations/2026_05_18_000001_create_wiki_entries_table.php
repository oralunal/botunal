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
        Schema::create('wiki_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // killer|survivor|perk|power|addon|term
            $table->string('name_en');
            $table->string('name_tr')->nullable();
            $table->string('slug', 191)->unique();
            $table->string('owner')->nullable()->index(); // character this belongs to
            $table->string('role')->nullable(); // survivor|killer (label) | null
            $table->text('description_tr')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_curated')->default(false);
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index('name_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiki_entries');
    }
};
