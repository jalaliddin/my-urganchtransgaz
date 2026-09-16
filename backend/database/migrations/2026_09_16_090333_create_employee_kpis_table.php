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
        Schema::create('employee_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_indicator_id')->constrained()->cascadeOnDelete();

            $table->decimal('target_value', 10, 2);
            $table->decimal('actual_value', 10, 2)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedTinyInteger('weight');
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->text('comment')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'kpi_period_id', 'kpi_indicator_id'], 'employee_kpi_unique');
            $table->index(['employee_id', 'kpi_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_kpis');
    }
};
