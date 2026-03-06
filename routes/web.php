<?php

use App\Http\Controllers\CobrosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cobros', [CobrosController::class, 'index'])->name('cobros.index');

Route::get('/cobros/{id}', [CobrosController::class, 'show'])->name('cobros.show');

Route::get('/cobros/{id}/proforma/preview', [CobrosController::class, 'previewProforma'])->name('cobros.proforma.preview');

