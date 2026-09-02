<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Procesar cuadre de marcaciones automáticamente cada día a las 23:55
Schedule::command('procesar:cuadre')->dailyAt('23:55');

// Cerrar acciones de personal vencidas (SUBROGACION, VACACIONES, COMISION DE SERVICIOS)
// — antes era efecto secundario de un GET, ver AccionPersonalController::index()
Schedule::command('cerrar:acciones-vencidas')->dailyAt('06:00');
