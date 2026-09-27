<?php

use App\Http\Controllers\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Enlace público de la cotización: por UUID, nunca por ID numérico (HU-016)
Route::get('/cotizacion/{uuid}', [DocumentoController::class, 'cotizacionPublica'])
    ->whereUuid('uuid')
    ->name('cotizacion.publica');

Route::middleware('auth')->prefix('documentos')->name('documentos.')->group(function () {
    Route::get('contratos/{contrato}/pdf', [DocumentoController::class, 'contrato'])->name('contrato');
    Route::get('contratos/{contrato}/evidencia', [DocumentoController::class, 'evidencia'])->name('evidencia');
    Route::get('cotizaciones/{cotizacion}/pdf', [DocumentoController::class, 'cotizacion'])->name('cotizacion');
});
