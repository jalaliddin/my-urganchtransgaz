<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both columns are nullable only so issues reported before this
     * migration keep working; the store request requires both going forward.
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('issue_category_id')->nullable()->after('department_id')
                ->constrained('issue_categories')->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->after('issue_category_id')
                ->constrained('employees')->nullOnDelete();

            $table->index(['issue_category_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['issue_category_id', 'organization_id']);
            $table->dropConstrainedForeignId('responsible_employee_id');
            $table->dropConstrainedForeignId('issue_category_id');
        });
    }
};
