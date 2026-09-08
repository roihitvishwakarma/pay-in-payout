<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resolve pending transactions on a schedule. withoutOverlapping() skips a run while
// the previous one is still going.
Schedule::command('payments:process')
    ->everyMinute()
    ->withoutOverlapping();
