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
        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->string('name_tr_norm')->nullable()->index()->after('name_tr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->dropIndex(['name_tr_norm']);
            $table->dropColumn('name_tr_norm');
        });
    }
};
