<?php

use App\Http\Controllers\CobrosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cobros', [CobrosController::class, 'index'])->name('cobros.index');
