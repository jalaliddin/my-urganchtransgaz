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
        Schema::create('kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weight');
            $table->decimal('target', 10, 2);
            $table->string('measurement_unit')->nullable();
            $table->string('calculation_type', 20);
            $table->string('period', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('kpi_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_indicators');
    }
};
