<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('display_name');
            $table->string('fingerprint')->nullable()->after('ip_address');
        });

        Schema::table('room_bans', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('display_name');
            $table->string('fingerprint')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('room_bans', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'fingerprint']);
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'fingerprint']);
        });
    }
};
