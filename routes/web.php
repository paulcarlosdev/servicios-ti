<?php

use App\Http\Controllers\DocumentoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

// Archivos públicos (logo y QR de la empresa) servidos por Laravel: en hosting compartido
// no se puede crear el enlace public/storage (php artisan storage:link).
Route::match(['GET', 'HEAD'], '/storage/{ruta}', function (string $ruta) {
    $disco = Storage::disk('public');
    abort_unless($disco->exists($ruta), 404);

    return $disco->response($ruta, null, [
        'Cache-Control' => 'public, max-age=86400',
        'Content-Length' => $disco->size($ruta),
    ]);
})->where('ruta', '.*')->name('storage.publico');
