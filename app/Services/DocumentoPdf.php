<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** PDF de contratos y cotizaciones (layout de docs/prototype/assets/documento.js). */
class DocumentoPdf
{
    public function contenido(Contrato|Cotizacion $doc): string
    {
        $doc->loadMissing(['cliente', 'lineas']);

        return Pdf::loadView('pdf.documento', [
            'doc' => $doc,
            'esContrato' => $doc instanceof Contrato,
            'empresa' => Empresa::actual(),
            'totales' => $doc->totales(),
        ])->setPaper('a4')->output();
    }

    /** Genera y guarda el PDF del contrato (se llama al guardar/activar/renovar). */
    public function guardar(Contrato $contrato): string
    {
        $path = "contratos/{$contrato->numero}.pdf";
        Storage::disk('local')->put($path, $this->contenido($contrato));
        $contrato->forceFill(['pdf_path' => $path])->saveQuietly();

        return $path;
    }
}
