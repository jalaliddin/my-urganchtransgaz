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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();

            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender', 10)->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('corporate_email')->nullable();

            $table->text('address')->nullable();
            // Encrypted ciphertext varies in length and per-value, so these are
            // stored as TEXT and cannot carry a meaningful unique constraint.
            $table->text('passport_number')->nullable();
            $table->text('pinfl')->nullable();

            $table->string('employment_type', 20)->default('full_time');

            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();

            $table->string('photo')->nullable();
            $table->string('status', 20)->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
