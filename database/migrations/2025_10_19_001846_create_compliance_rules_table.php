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
        Schema::create('compliance_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('squad_id');
            $table->string('rule_name');
            $table->text('description')->nullable();
            $table->enum('rule_type', ['minimum_hours', 'late_arrival', 'early_checkout', 'attendance_rate', 'custom']);
            $table->json('rule_config'); // Flexible config for different rule types
            $table->integer('severity')->default(1)->comment('1-5, 5 being most severe');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('squad_id')->references('id')->on('squads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_rules');
    }
};
