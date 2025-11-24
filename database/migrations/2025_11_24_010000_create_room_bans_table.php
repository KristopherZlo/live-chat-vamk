<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token');
            $table->string('display_name')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bans');
    }
};
