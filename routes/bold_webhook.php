<?php

use App\Http\Controllers\Portal\BoldWebhookController;
use Illuminate\Support\Facades\Route;

// Intentionally outside web/auth/session middleware. No global CSRF exception.
Route::post('/portal/pagos/bold/webhook', BoldWebhookController::class)
    ->name('portal.bold.webhook');
