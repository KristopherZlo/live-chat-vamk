<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('deleted_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('deleted_by_participant_id')
                ->nullable()
                ->after('deleted_by_user_id')
                ->constrained('participants')
                ->nullOnDelete();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropConstrainedForeignId('deleted_by_participant_id');
            $table->dropSoftDeletes();
        });
    }
};
