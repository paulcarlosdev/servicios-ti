<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Services\DocumentoPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentoController extends Controller
{
    public function __construct(private DocumentoPdf $pdf) {}

    public function contrato(Request $request, Contrato $contrato): Response
    {
        abort_unless($request->user()->puedeVer('montos'), 403);

        return $this->responder($this->pdf->contenido($contrato), $contrato->nombrePdf(), $request->boolean('descargar'));
    }

    public function cotizacion(Request $request, Cotizacion $cotizacion): Response
    {
        abort_unless($request->user()->puedeVer('montos'), 403);

        return $this->responder($this->pdf->contenido($cotizacion), $cotizacion->nombrePdf(), $request->boolean('descargar'));
    }

    public function cotizacionPublica(Request $request, string $uuid): Response
    {
        $cotizacion = Cotizacion::where('uuid', $uuid)->firstOrFail();

        return $this->responder($this->pdf->contenido($cotizacion), $cotizacion->nombrePdf(), $request->boolean('descargar'));
    }

    public function evidencia(Request $request, Contrato $contrato): Response
    {
        abort_unless($request->user()->puedeVer('documentos'), 403);
        abort_unless($contrato->documento_evidencia_path && Storage::disk('local')->exists($contrato->documento_evidencia_path), 404);

        return Storage::disk('local')->download($contrato->documento_evidencia_path, $contrato->documento_evidencia_nombre);
    }

    private function responder(string $contenido, string $nombre, bool $descargar): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($descargar ? 'attachment' : 'inline').'; filename="'.$nombre.'"',
        ]);
    }
}
