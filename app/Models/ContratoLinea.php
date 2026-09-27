<?php

namespace App\Models;

use App\Models\Concerns\EsLinea;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class ContratoLinea extends Model
{
    use EsLinea;

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function inicioDocumento(): ?CarbonInterface
    {
        return $this->contrato?->fecha_inicio;
    }

    public function tipoCambio(): float
    {
        return (float) ($this->contrato?->tipo_cambio ?? 1);
    }
}
