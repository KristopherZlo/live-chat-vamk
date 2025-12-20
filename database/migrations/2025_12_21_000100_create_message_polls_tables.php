<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('question', 255);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique('message_id');
        });

        Schema::create('message_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('message_polls')->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('message_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('message_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('message_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
            $table->unique(['poll_id', 'participant_id']);
            $table->index(['poll_id', 'option_id'], 'message_poll_votes_poll_option_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_poll_votes');
        Schema::dropIfExists('message_poll_options');
        Schema::dropIfExists('message_polls');
    }
};
