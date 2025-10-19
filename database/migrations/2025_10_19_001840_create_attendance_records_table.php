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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('squad_id');
            $table->uuid('sprint_id')->nullable();
            $table->date('date');
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->enum('work_mode', ['remote', 'office', 'client_site', 'ooo'])->default('office');
            $table->enum('event_tag', ['standup', 'retro', 'planning', 'demo', 'regular'])->default('regular');
            $table->enum('status', ['full_day', 'partial_day', 'leave'])->default('full_day');
            $table->string('check_in_ip')->nullable();
            $table->string('check_out_ip')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_hours')->nullable();
            $table->timestamps();

            $table->foreign('squad_id')->references('id')->on('squads')->onDelete('cascade');
            $table->foreign('sprint_id')->references('id')->on('sprints')->onDelete('set null');
            $table->index(['user_id', 'date']);
            $table->index(['squad_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
