<?php

namespace App\Models;

use App\Models\Concerns\EsLinea;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class CotizacionLinea extends Model
{
    use EsLinea;

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function inicioDocumento(): ?CarbonInterface
    {
        return $this->cotizacion?->fecha;
    }

    public function tipoCambio(): float
    {
        return (float) ($this->cotizacion?->tipo_cambio ?? 1);
    }
}
