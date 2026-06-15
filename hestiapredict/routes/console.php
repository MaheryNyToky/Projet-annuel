<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Http\Controllers\HotelManagementController;
use Illuminate\Support\Facades\Schedule;

// Tous les jours à 19h00, on bascule les retards en "À appeler"
Schedule::call(function () {
    app(HotelManagementController::class)->checkLateArrivals();
})->dailyAt('19:00');

// Toutes les heures, on vérifie si l'hôtel se remplit trop pour envoyer l'alerte e-mail "Stop-Booking"
Schedule::call(function () {
    app(HotelManagementController::class)->checkOccupationAndAlert();
})->hourly();
