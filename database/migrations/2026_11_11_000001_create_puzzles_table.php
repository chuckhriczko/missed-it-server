<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('puzzles', function (Blueprint $table) {
            $table->id();
            $table->uuid('slug')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 56);
            $table->string('brief', 220);
            $table->text('notes')->nullable();
            $table->string('difficulty', 16)->index();
            $table->longText('document');
            $table->char('checksum', 64);
            $table->json('mechanics');
            $table->boolean('has_replay')->default(false);
            $table->unsignedBigInteger('downloads')->default(0)->index();
            $table->timestamps();
            $table->unique(['user_id', 'checksum']);
            $table->index('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('puzzles'); }
};
