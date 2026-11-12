<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// How many players landed HOME on a downloaded copy (reported by the game, POST /api/v1/puzzles/{id}/clears).
return new class extends Migration {
    public function up(): void { Schema::table('puzzles', fn (Blueprint $table) => $table->unsignedBigInteger('clears')->default(0)->index()); }
    public function down(): void { Schema::table('puzzles', fn (Blueprint $table) => $table->dropColumn('clears')); }
};
