<?php

use App\Console\Commands\PruneOldSearchHistory;
use App\Console\Commands\ScrapeCatalogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PruneOldSearchHistory::class)->weekly();
Schedule::command(ScrapeCatalogs::class)->dailyAt('04:00')->withoutOverlapping()->onOneServer();
