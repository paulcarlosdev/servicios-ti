<?php

namespace App\Models;

use App\Enums\Estado;
use App\Models\Concerns\EsDocumento;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cotización: pendiente → aceptada → convertida, o rechazada. "Expirada" se deriva de valida_hasta. */
#[Table('cotizaciones')]
#[Guarded(['id'])]
class Cotizacion extends Model
{
    use EsDocumento;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valida_hasta' => 'date',
            'tipo_cambio' => 'float',
            'aplica_igv' => 'boolean',
            'total_usd' => 'float',
            'total_pen' => 'float',
        ];
    }

    public static function prefijo(): string
    {
        return 'COT';
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CotizacionLinea::class)->orderBy('orden')->orderBy('id');
    }

    public function estadoVisible(): Estado
    {
        return $this->estado === 'pendiente' && $this->valida_hasta?->lt(today())
            ? Estado::Expirada
            : Estado::from($this->estado);
    }

    public function pendiente(): bool
    {
        return $this->estadoVisible() === Estado::Pendiente;
    }

    public function scopeEnEstado(Builder $query, string $estado): void
    {
        match ($estado) {
            'pendiente' => $query->where('estado', 'pendiente')->where('valida_hasta', '>=', today()),
            'expirada' => $query->where('estado', 'pendiente')->where('valida_hasta', '<', today()),
            default => $query->where('estado', $estado),
        };
    }

    public function urlPublica(): string
    {
        return route('cotizacion.publica', $this->uuid);
    }

    public function nombrePdf(): string
    {
        return "{$this->numero}.pdf";
    }
}
