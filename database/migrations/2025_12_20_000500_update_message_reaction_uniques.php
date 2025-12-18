<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropUnique(['message_id', 'emoji', 'user_id']);
            $table->dropUnique(['message_id', 'emoji', 'participant_id']);
        });

        $this->dedupeReactions('user_id');
        $this->dedupeReactions('participant_id');

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->unique(['message_id', 'user_id']);
            $table->unique(['message_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropUnique(['message_id', 'user_id']);
            $table->dropUnique(['message_id', 'participant_id']);
        });

        Schema::table('message_reactions', function (Blueprint $table) {
            $table->unique(['message_id', 'emoji', 'user_id']);
            $table->unique(['message_id', 'emoji', 'participant_id']);
        });
    }

    private function dedupeReactions(string $column): void
    {
        $keepIds = DB::table('message_reactions')
            ->select(DB::raw('MAX(id) as id'))
            ->whereNotNull($column)
            ->groupBy('message_id', $column)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        DB::table('message_reactions')
            ->whereNotNull($column)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
};
