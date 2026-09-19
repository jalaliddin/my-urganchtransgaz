<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An issue now has one *or more* executors (same shape as
     * `task_assignees`), replacing the single `responsible_employee_id`.
     * Anything already assigned is carried over as that issue's first
     * executor, so nothing is lost on a database that has data.
     */
    public function up(): void
    {
        Schema::create('issue_executors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['issue_id', 'employee_id']);
        });

        DB::table('issue_executors')->insertUsing(
            ['issue_id', 'employee_id', 'created_at', 'updated_at'],
            DB::table('issues')
                ->whereNotNull('responsible_employee_id')
                ->selectRaw('id, responsible_employee_id, ?, ?', [now(), now()])
        );

        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_employee_id');
        });
    }

    /**
     * Reverse the migrations. Only the first executor can be kept, since the
     * old column holds exactly one.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('responsible_employee_id')->nullable()->after('issue_category_id')
                ->constrained('employees')->nullOnDelete();
        });

        DB::statement(
            'UPDATE issues SET responsible_employee_id = (
                SELECT employee_id FROM issue_executors
                WHERE issue_executors.issue_id = issues.id ORDER BY id LIMIT 1
            )'
        );

        Schema::dropIfExists('issue_executors');
    }
};
