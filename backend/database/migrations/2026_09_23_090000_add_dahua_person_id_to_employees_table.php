<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The person id a Dahua access-control terminal was enrolled with for
     * this employee (its `UserID`, set on the device itself when the
     * person's card/face/fingerprint was registered there) — how the
     * on-site bridge app's webhook resolves a scan back to an employee,
     * as an alternative to `employee_number` for sites that only know the
     * device-side id. Nullable: most employees are never enrolled on a
     * device at all.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('dahua_person_id')->nullable()->unique()->after('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['dahua_person_id']);
            $table->dropColumn('dahua_person_id');
        });
    }
};
