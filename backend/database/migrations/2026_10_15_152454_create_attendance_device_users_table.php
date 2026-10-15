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
        // The people enrolled on each terminal, as its client last reported
        // them — what the Dahua terminals page compares with employees.
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('user_id', 64);
            $table->string('name')->nullable();
            $table->string('card_no', 64)->nullable();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->timestamp('roster_synced_at')->nullable()->after('last_used_at');
            $table->boolean('roster_complete')->default(true)->after('roster_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['roster_synced_at', 'roster_complete']);
        });

        Schema::dropIfExists('attendance_device_users');
    }
};
