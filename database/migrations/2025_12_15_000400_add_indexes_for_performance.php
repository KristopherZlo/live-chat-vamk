<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['room_id', 'created_at'], 'messages_room_created_at_index');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['room_id', 'status', 'created_at'], 'questions_room_status_created_at_index');
            $table->index(['participant_id', 'created_at'], 'questions_participant_created_at_index');
        });

        Schema::table('room_bans', function (Blueprint $table) {
            $table->index(['room_id', 'ip_address'], 'room_bans_room_ip_index');
            $table->index(['room_id', 'fingerprint'], 'room_bans_room_fp_index');
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->index('message_id', 'message_reactions_message_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_room_created_at_index');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_room_status_created_at_index');
            $table->dropIndex('questions_participant_created_at_index');
        });

        Schema::table('room_bans', function (Blueprint $table) {
            $table->dropIndex('room_bans_room_ip_index');
            $table->dropIndex('room_bans_room_fp_index');
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropIndex('message_reactions_message_id_index');
        });
    }
};
