<?php

use Illuminate\Support\Facades\Route;
use Modules\Ejemplo\Http\Controllers\EjemploController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('ejemplos', EjemploController::class)->names('ejemplo');
});
