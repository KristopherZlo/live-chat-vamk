<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->index(
                ['room_id', 'deleted_by_owner_at', 'deleted_by_participant_at', 'status', 'created_at'],
                'questions_room_deleted_status_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_room_deleted_status_created_index');
        });
    }
};
