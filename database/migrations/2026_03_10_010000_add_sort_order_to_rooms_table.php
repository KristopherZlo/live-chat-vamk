<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->index();
        });

        $rows = DB::table('rooms')
            ->select(['id', 'user_id'])
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $orderByUser = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $orderByUser[$userId] = ($orderByUser[$userId] ?? 0) + 1;
            DB::table('rooms')
                ->where('id', $row->id)
                ->update(['sort_order' => $orderByUser[$userId]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
