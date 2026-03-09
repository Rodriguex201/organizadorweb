<?php

use App\Http\Controllers\CobrosController;
use App\Http\Controllers\ProformasController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cobros', [CobrosController::class, 'index'])->name('cobros.index');

Route::get('/cobros/{id}', [CobrosController::class, 'show'])->name('cobros.show');

Route::get('/cobros/{id}/proforma/preview', [CobrosController::class, 'previewProforma'])->name('cobros.proforma.preview');
Route::post('/cobros/{id}/proforma', [CobrosController::class, 'storeProforma'])->name('cobros.proforma.store');

Route::get('/proformas', [ProformasController::class, 'index'])->name('proformas.index');

Route::get('/proformas/dashboard', [ProformasController::class, 'dashboard'])->name('proformas.dashboard');

Route::get('/proformas/{id}', [ProformasController::class, 'show'])->name('proformas.show');
Route::get('/proformas/{id}/pdf', [ProformasController::class, 'showPdf'])->name('proformas.pdf.show');
Route::get('/proformas/{id}/pdf/download', [ProformasController::class, 'downloadPdf'])->name('proformas.pdf.download');


Route::patch('/proformas/{id}/estado', [ProformasController::class, 'updateEstado'])->name('proformas.estado.update');

