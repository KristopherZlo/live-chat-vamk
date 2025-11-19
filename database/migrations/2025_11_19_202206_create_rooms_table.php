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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // владелец
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique(); // публичный идентификатор (ссылка)
            $table->enum('status', ['active', 'finished', 'archived'])->default('active');
            $table->boolean('is_public_read')->default(true); // можно ли читать завершённый чат
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
