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
        Schema::create('kpi_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period_type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['period_type', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_periods');
    }
};
