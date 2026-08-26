<?php

use Illuminate\Support\Facades\Route;
use Modules\FormBuilder\Http\Controllers\TicketFormLinkPdfController;
use Modules\FormBuilder\Livewire\Forms\Builder;
use Modules\FormBuilder\Livewire\Forms\Manage;
use Modules\FormBuilder\Livewire\Links\Send;
use Modules\FormBuilder\Livewire\Links\Show;
use Modules\FormBuilder\Livewire\Public\FillTicketForm;

Route::middleware(['auth', 'permission:screens.formbuilder.manage'])->group(function () {
    Route::get('/formularios', Manage::class)->name('formbuilder.forms.index');
    Route::get('/formularios/{form}/construir', Builder::class)->name('formbuilder.forms.builder');
});

Route::middleware(['auth', 'permission:screens.formbuilder.capture'])->group(function () {
    Route::get('/mis-formularios', Send::class)->name('formbuilder.links.index');
    Route::get('/mis-formularios/{ticketFormLink}', Show::class)->name('formbuilder.links.show');
    Route::get('/mis-formularios/{ticketFormLink}/imprimir', [TicketFormLinkPdfController::class, 'internal'])->name('formbuilder.links.print');
});

Route::middleware(['throttle:public-form-pages'])->group(function () {
    Route::get('/formularios-publicos/{token}', FillTicketForm::class)->name('formbuilder.public.fill');
});

Route::middleware(['throttle:public-form-pages', 'signed'])->group(function () {
    Route::get('/formularios-publicos/{ticketFormLink}/imprimir', [TicketFormLinkPdfController::class, 'public'])->name('formbuilder.public.print');
});
