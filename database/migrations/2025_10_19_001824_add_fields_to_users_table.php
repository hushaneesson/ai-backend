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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', ['admin', 'squad_lead', 'member', 'viewer'])->default('member');
            $table->string('timezone')->default('UTC');
            $table->string('provider')->nullable(); // google, microsoft
            $table->string('provider_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'role', 'timezone', 'provider', 'provider_id', 'is_active', 'last_seen_at']);
        });
    }
};
