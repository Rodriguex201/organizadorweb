<?php

use App\Http\Controllers\Portal\MockPortalController;
use Illuminate\Support\Facades\Route;

// Maqueta sin middleware web, sesiones, permisos administrativos ni persistencia.
Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::match(['GET', 'POST'], '/revision', \App\Http\Controllers\Portal\ReadOnlyProformaController::class)
        ->middleware(\App\Http\Middleware\LocalPortalReview::class)->name('revision');
    Route::get('/', \App\Http\Controllers\Portal\ReadOnlyProformaController::class)
        ->middleware(\App\Http\Middleware\LocalPortalReview::class)->name('inicio');
    Route::get('/verificacion', [MockPortalController::class, 'verificacion'])->name('verificacion');
    Route::get('/proforma', \App\Http\Controllers\Portal\ReadOnlyProformaController::class)
        ->middleware(\App\Http\Middleware\LocalPortalReview::class)->name('proforma');
    Route::get('/resultado/{estado}', [MockPortalController::class, 'resultado'])
        ->where('estado', 'aprobado|pendiente|rechazado')->name('resultado');
    Route::get('/activacion/{estado}', [MockPortalController::class, 'activacion'])
        ->where('estado', 'pendiente|completada|incidencia')->name('activacion');
});
