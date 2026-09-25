<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a day to the absence that excused it, so cancelling or
     * editing that absence can put the day back exactly as it was.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('employee_absence_id')->nullable()->after('source')
                ->constrained('employee_absences')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_absence_id');
        });
    }
};
