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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete(); // ссылка на сообщение в чате
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // вдруг владелец сам добавит вопрос
            $table->text('content');
            $table->enum('status', ['new', 'answered', 'ignored', 'later'])->default('new');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('deleted_by_participant_at')->nullable();
            $table->timestamp('deleted_by_owner_at')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
