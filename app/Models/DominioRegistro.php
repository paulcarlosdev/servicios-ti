<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dominio registrado a un cliente. No se elimina: se da de baja. */
#[Guarded(['id'])]
class DominioRegistro extends Model
{
    protected function casts(): array
    {
        return ['registrado_at' => 'date'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(DominioTipo::class, 'dominio_tipo_id');
    }

    public function getDominioAttribute(): string
    {
        return $this->nombre.$this->tipo?->extension;
    }

    /** Línea de contrato no cancelado que factura este dominio. */
    public function lineaContrato(): ?ContratoLinea
    {
        return ContratoLinea::query()
            ->with('contrato')
            ->where('tipo', 'dominio')
            ->where('identificador', $this->dominio)
            ->whereHas('contrato', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->latest('id')
            ->first();
    }
}
