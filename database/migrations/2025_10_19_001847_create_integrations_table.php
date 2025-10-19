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
        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('squad_id')->nullable();
            $table->enum('type', ['google_calendar', 'outlook', 'slack', 'ms_teams', 'jira', 's3'])->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('config'); // API keys, tokens, etc. (encrypted)
            $table->timestamp('last_sync_at')->nullable();
            $table->json('sync_status')->nullable();
            $table->timestamps();

            $table->foreign('squad_id')->references('id')->on('squads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
