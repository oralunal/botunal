<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('id');
        });

        DB::table('timers')->orderBy('id')->pluck('id')->each(function (int $id, int $index): void {
            DB::table('timers')->where('id', $id)->update(['position' => $index + 1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
