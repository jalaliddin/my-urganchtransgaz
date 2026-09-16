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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('new');

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('result')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
