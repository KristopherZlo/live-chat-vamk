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
        Schema::create('client_error_reports', function (Blueprint $table) {
            $table->id();
            $table->string('severity', 16)->default('error')->index();
            $table->text('message');
            $table->text('stack')->nullable();
            $table->string('url', 2048);
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('column')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_error_reports');
    }
};
