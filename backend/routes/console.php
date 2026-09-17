<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:check-expiration')->dailyAt('07:00');
Schedule::command('attendance:mark-absentees')->dailyAt('00:30');
Schedule::command('tasks:check-deadlines')->dailyAt('07:15');
Schedule::command('exams:send-reminders')->dailyAt('07:30');
Schedule::command('announcements:process-schedule')->everyFiveMinutes();
