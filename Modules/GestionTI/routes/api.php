<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionTI\Http\Controllers\GestionTIController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('gestiontis', GestionTIController::class)->names('gestionti');
});
