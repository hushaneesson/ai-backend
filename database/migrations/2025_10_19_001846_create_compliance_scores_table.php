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
        Schema::create('compliance_scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('squad_id');
            $table->uuid('sprint_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('period_type', ['weekly', 'sprint'])->default('weekly');
            $table->decimal('score', 5, 2)->default(0); // 0-100
            $table->json('violations')->nullable(); // Array of violations
            $table->json('metrics')->nullable(); // Detailed metrics
            $table->timestamps();

            $table->foreign('squad_id')->references('id')->on('squads')->onDelete('cascade');
            $table->foreign('sprint_id')->references('id')->on('sprints')->onDelete('set null');
            $table->index(['squad_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_scores');
    }
};
