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
        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('document_number', 100)->nullable();
            $table->date('document_date')->nullable();
            $table->string('destination')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_absences');
    }
};
