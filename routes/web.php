<?php

use App\Http\Controllers\CiudadesController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\CobrosController;
use App\Http\Controllers\ConfiguracionEstadoProformaController;
use App\Http\Controllers\ProformasController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('auth.custom')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/clientes')->name('home');

    Route::get('/ciudades/buscar', [CiudadesController::class, 'buscar'])->name('ciudades.buscar');

    Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/create', [ClientesController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClientesController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{id}', [ClientesController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{id}/edit', [ClientesController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{id}', [ClientesController::class, 'update'])->name('clientes.update');
    Route::patch('/clientes/{id}/retirar', [ClientesController::class, 'retirar'])->name('clientes.retirar');

    Route::get('/cobros', [CobrosController::class, 'index'])->name('cobros.index');

    Route::get('/cobros/{id}', [CobrosController::class, 'show'])->name('cobros.show');
    Route::get('/cobros/{id}/revisar', [CobrosController::class, 'revisar'])->name('cobros.revisar');
    Route::post('/cobros/{id}/revisar', [CobrosController::class, 'guardarRevision'])->name('cobros.revisar.guardar');

    Route::get('/cobros/{id}/proforma/preview', [CobrosController::class, 'previewProforma'])->name('cobros.proforma.preview');
    Route::post('/cobros/{id}/proforma', [CobrosController::class, 'storeProforma'])->name('cobros.proforma.store');

    Route::get('/proformas', [ProformasController::class, 'index'])->name('proformas.index');

    Route::get('/proformas/dashboard', [ProformasController::class, 'dashboard'])->name('proformas.dashboard');

    Route::middleware('role.admin')->group(function (): void {
        Route::get('/proformas/envio-masivo/{grupo}/confirmar', [ProformasController::class, 'confirmarEnvioMasivo'])->name('proformas.envio-masivo.confirmar');
        Route::post('/proformas/envio-masivo/{grupo}', [ProformasController::class, 'enviarMasivo'])->name('proformas.envio-masivo.enviar');
    });

    Route::get('/proformas/{id}', [ProformasController::class, 'show'])->name('proformas.show');
    Route::get('/proformas/{id}/pdf', [ProformasController::class, 'showPdf'])->name('proformas.pdf.show');
    Route::get('/proformas/{id}/pdf/download', [ProformasController::class, 'downloadPdf'])->name('proformas.pdf.download');
    Route::post('/proformas/{id}/enviar', [ProformasController::class, 'enviarCorreo'])->name('proformas.enviar');

    Route::patch('/proformas/{id}/estado', [ProformasController::class, 'updateEstado'])
        ->middleware('role:admin,user')
        ->name('proformas.estado.update');

    Route::middleware('role.admin')->group(function (): void {
        Route::get('/configuracion/estados-proforma', [ConfiguracionEstadoProformaController::class, 'index'])->name('configuracion.estados-proforma.index');
        Route::patch('/configuracion/estados-proforma/{estadoCodigo}', [ConfiguracionEstadoProformaController::class, 'update'])->name('configuracion.estados-proforma.update');
    });
});
