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
        Schema::create('wiki_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_entry_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_norm')->index();
            $table->timestamps();

            $table->unique(['wiki_entry_id', 'alias_norm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiki_aliases');
    }
};
