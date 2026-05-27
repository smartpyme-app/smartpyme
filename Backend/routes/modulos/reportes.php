<?php

use App\Http\Controllers\Api\ReportesController;

// ─── Reportes financieros ────────────────────────────────────────────────────

Route::get('/reportes/flujo-efectivo', [ReportesController::class, 'flujoEfectivo']);
