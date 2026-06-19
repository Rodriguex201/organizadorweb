<?php

use App\Http\Controllers\CiudadesController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConfiguracionConceptoController;
use App\Http\Controllers\CobrosController;
use App\Http\Controllers\DebugEmpresaServidorController;
use App\Http\Controllers\EstadoCuentaProformasController;
use App\Http\Controllers\ConfiguracionDirectorioController;
use App\Http\Controllers\ConfiguracionEstadoProformaController;
use App\Http\Controllers\ConfiguracionUsuarioController;
use App\Http\Controllers\ImportacionesController;
use App\Http\Controllers\ConfiguracionTarifaController;
use App\Http\Controllers\ProformaCarteraController;
use App\Http\Controllers\ProformasController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('auth.custom')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/clientes')->name('home');

    Route::get('/ciudades/buscar', [CiudadesController::class, 'buscar'])->name('ciudades.buscar');
    Route::get('/debug/empresa-servidor/{codigo}', [DebugEmpresaServidorController::class, 'show'])
        ->middleware('role.admin')
        ->name('debug.empresa-servidor.show');

    Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/create', [ClientesController::class, 'create'])->name('clientes.create');
    Route::get('/clientes/codigo/disponibilidad', [ClientesController::class, 'checkCodigoAvailability'])->name('clientes.codigo.disponibilidad');
    Route::get('/clientes/codigo/siguiente', [ClientesController::class, 'nextCodigo'])->name('clientes.codigo.siguiente');
    Route::post('/clientes', [ClientesController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{id}', [ClientesController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{id}/edit', [ClientesController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{id}', [ClientesController::class, 'update'])->name('clientes.update');
    Route::patch('/clientes/{id}/activar-facturacion', [ClientesController::class, 'activarFacturacion'])->name('clientes.activar-facturacion');
    Route::patch('/clientes/{id}/retirar', [ClientesController::class, 'retirar'])->name('clientes.retirar');
    Route::patch('/clientes/{id}/reactivar', [ClientesController::class, 'reactivar'])->name('clientes.reactivar');

    Route::get('/cobros', [CobrosController::class, 'index'])->name('cobros.index');
    Route::get('/cobros/extraordinario/crear', [CobrosController::class, 'createExtraordinary'])->name('cobros.extraordinario.create');
    Route::post('/cobros/extraordinario', [CobrosController::class, 'storeExtraordinary'])->name('cobros.extraordinario.store');
    Route::post('/cobros/lote-pendiente/limpiar', [CobrosController::class, 'limpiarLotePendienteEnvio'])->name('cobros.lote-pendiente.limpiar');
    Route::post('/cobros/proformas-masivo/{grupo}', [CobrosController::class, 'generarProformasMasivo'])->name('cobros.proformas-masivo');
    Route::post('/cobros/proformas-masivo/{grupo}/enviar', [CobrosController::class, 'enviarProformasMasivo'])->name('cobros.proformas-masivo.enviar');
    Route::post('/cobros/proformas-masivo/{grupo}/pendientes/activar', [CobrosController::class, 'activarPendientesFacturacionMasivo'])->name('cobros.proformas-masivo.pendientes.activar');
    Route::post('/cobros/proformas-masivo/{grupo}/pendientes/regenerar', [CobrosController::class, 'regenerarPendientesFacturacionMasivo'])->name('cobros.proformas-masivo.pendientes.regenerar');
    Route::post('/cobros/proformas-masivo/{grupo}/pendientes/descartar', [CobrosController::class, 'descartarRegeneracionPendientesFacturacionMasivo'])->name('cobros.proformas-masivo.pendientes.descartar');

    Route::get('/cobros/{id}', [CobrosController::class, 'show'])->name('cobros.show');
    Route::patch('/cobros/clientes/{id}/nota', [CobrosController::class, 'updateNotaCobro'])->name('cobros.nota.update');
    Route::delete('/cobros/clientes/{id}/nota', [CobrosController::class, 'clearNotaCobro'])->name('cobros.nota.clear');
    Route::get('/cobros/{id}/revisar', [CobrosController::class, 'revisar'])->name('cobros.revisar');
    Route::post('/cobros/{id}/revisar', [CobrosController::class, 'guardarRevision'])->name('cobros.revisar.guardar');

    Route::get('/cobros/{id}/proforma/preview', [CobrosController::class, 'previewProforma'])->name('cobros.proforma.preview');
    Route::post('/cobros/{id}/proforma', [CobrosController::class, 'storeProforma'])->name('cobros.proforma.store');
    Route::post('/cobros/{id}/proforma/regenerar', [CobrosController::class, 'regenerateProforma'])->name('cobros.proforma.regenerar');

    Route::get('/proformas', [ProformasController::class, 'index'])->name('proformas.index');
    Route::get('/proformas/limpiar-filtros', [ProformasController::class, 'clearFilters'])->name('proformas.clear-filters');
    Route::get('/proformas/estado-cuenta', [EstadoCuentaProformasController::class, 'index'])->name('proformas.estado-cuenta.index');
    Route::post('/proformas/estado-cuenta/pdf', [EstadoCuentaProformasController::class, 'pdf'])->name('proformas.estado-cuenta.pdf');
    Route::get('/proformas/{id}/volver', [ProformasController::class, 'backToIndex'])->name('proformas.back-to-index');

    Route::get('/proformas/dashboard', [ProformasController::class, 'dashboard'])->name('proformas.dashboard');
    Route::post('/proformas/dashboard/export', [ProformasController::class, 'exportDashboard'])->name('proformas.dashboard.export');
    Route::get('/proformas/dashboard/export/download/{token}', [ProformasController::class, 'downloadDashboardExport'])->name('proformas.dashboard.export.download');
    Route::get('/proformas/cartera', [ProformaCarteraController::class, 'index'])->name('proformas.cartera.index');
    Route::post('/proformas/cartera/export', [ProformaCarteraController::class, 'export'])->name('proformas.cartera.export');

    Route::middleware('role.admin')->group(function (): void {
        Route::get('/proformas/envio-masivo/{grupo}/confirmar', [ProformasController::class, 'confirmarEnvioMasivo'])->name('proformas.envio-masivo.confirmar');
        Route::post('/proformas/envio-masivo/{grupo}', [ProformasController::class, 'enviarMasivo'])->name('proformas.envio-masivo.enviar');
        Route::get('/proformas/{id}/activacion', [ProformasController::class, 'obtenerActivacion'])->name('proformas.activacion.show');
        Route::post('/proformas/{id}/activacion', [ProformasController::class, 'guardarActivacion'])->name('proformas.activacion.update');
        Route::post('/proformas/{id}/activacion/eventos', [ProformasController::class, 'actualizarLicenciaEventos'])->name('proformas.activacion.eventos.update');
    });

    Route::get('/proformas/{id}', [ProformasController::class, 'show'])->name('proformas.show');
    Route::get('/proformas/{id}/pdf', [ProformasController::class, 'showPdf'])->name('proformas.pdf.show');
    Route::get('/proformas/{id}/pdf/download', [ProformasController::class, 'downloadPdf'])->name('proformas.pdf.download');
    Route::post('/proformas/{id}/enviar', [ProformasController::class, 'enviarCorreo'])->name('proformas.enviar');
    Route::post('/proformas/{id}/marcar-enviada', [ProformasController::class, 'marcarEnviada'])
        ->middleware('role:admin,user')
        ->name('proformas.marcar-enviada');
    Route::post('/proformas/{id}/marcar-no-enviada', [ProformasController::class, 'marcarNoEnviada'])
        ->middleware('role:admin,user')
        ->name('proformas.marcar-no-enviada');

    Route::patch('/proformas/{id}/estado', [ProformasController::class, 'updateEstado'])
        ->middleware('role:admin,user')
        ->name('proformas.estado.update');

    Route::middleware('role:admin,user')->group(function (): void {
        Route::get('/configuracion/directorio', [ConfiguracionDirectorioController::class, 'index'])->name('configuracion.directorio.index');
        Route::get('/configuracion/estados-proforma', [ConfiguracionEstadoProformaController::class, 'index'])->name('configuracion.estados-proforma.index');
        Route::get('/configuracion/conceptos', [ConfiguracionConceptoController::class, 'index'])->name('configuracion.conceptos.index');
        Route::get('/configuracion/tarifas', [ConfiguracionTarifaController::class, 'index'])->name('configuracion.tarifas.index');
        Route::get('/configuracion/importaciones', [ImportacionesController::class, 'index'])->name('configuracion.importaciones.index');
        Route::post('/configuracion/importaciones/preview', [ImportacionesController::class, 'preview'])->name('configuracion.importaciones.preview');
        Route::post('/configuracion/importaciones/extract', [ImportacionesController::class, 'extract'])->name('configuracion.importaciones.extract');
        Route::post('/configuracion/importaciones/generate-base', [ImportacionesController::class, 'generateBase'])->name('configuracion.importaciones.generate-base');
        Route::post('/configuracion/importaciones/assign-ambiguous', [ImportacionesController::class, 'assignAmbiguous'])->name('configuracion.importaciones.assign-ambiguous');
        Route::post('/configuracion/importaciones/clear', [ImportacionesController::class, 'clear'])->name('configuracion.importaciones.clear');
    });

    Route::middleware('role.admin')->group(function (): void {
        Route::put('/configuracion/directorio', [ConfiguracionDirectorioController::class, 'update'])->name('configuracion.directorio.update');
        Route::patch('/configuracion/estados-proforma/{estadoCodigo}', [ConfiguracionEstadoProformaController::class, 'update'])->name('configuracion.estados-proforma.update');
        Route::post('/configuracion/conceptos', [ConfiguracionConceptoController::class, 'store'])->name('configuracion.conceptos.store');
        Route::put('/configuracion/conceptos/{concepto}', [ConfiguracionConceptoController::class, 'update'])->name('configuracion.conceptos.update');
        Route::patch('/configuracion/conceptos/{concepto}/toggle', [ConfiguracionConceptoController::class, 'toggle'])->name('configuracion.conceptos.toggle');
        Route::delete('/configuracion/conceptos/{concepto}', [ConfiguracionConceptoController::class, 'destroy'])->name('configuracion.conceptos.destroy');
        Route::put('/configuracion/tarifas', [ConfiguracionTarifaController::class, 'update'])->name('configuracion.tarifas.update');
        Route::get('/configuracion/usuarios', [ConfiguracionUsuarioController::class, 'index'])->name('configuracion.usuarios.index');
        Route::get('/configuracion/usuarios/crear', [ConfiguracionUsuarioController::class, 'create'])->name('configuracion.usuarios.create');
        Route::post('/configuracion/usuarios', [ConfiguracionUsuarioController::class, 'store'])->name('configuracion.usuarios.store');
        Route::get('/configuracion/usuarios/{usuario}/editar', [ConfiguracionUsuarioController::class, 'edit'])->name('configuracion.usuarios.edit');
        Route::put('/configuracion/usuarios/{usuario}', [ConfiguracionUsuarioController::class, 'update'])->name('configuracion.usuarios.update');
    });
});
