<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZktecoController;

Route::get('/', function () {
    return view('welcome');
});

// Endpoints ADMS para reloj biométrico ZKTeco — sin CSRF ni autenticación Sanctum
Route::prefix('iclock')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->group(function () {
        Route::post('cdata',                          [ZktecoController::class, 'cdata']);
        Route::get('getrequest',                      [ZktecoController::class, 'getrequest']);
        Route::match(['get', 'post'], 'registry',     [ZktecoController::class, 'registry']);
        Route::post('devicecmd',                      [ZktecoController::class, 'devicecmd']);
    });
