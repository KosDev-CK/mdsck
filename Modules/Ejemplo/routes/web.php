<?php

use Illuminate\Support\Facades\Route;
use Modules\Ejemplo\Livewire\Index;

Route::middleware(['auth', 'permission:screens.ejemplo.manage'])->group(function () {
    Route::get('/ejemplo', Index::class)->name('ejemplo.index');
});
