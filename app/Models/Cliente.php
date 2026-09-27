<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Guarded(['id'])]
class Cliente extends Model
{
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function dominios(): HasMany
    {
        return $this->hasMany(DominioRegistro::class);
    }

    /** Líneas de contratos vigentes (no borrador ni cancelado). */
    public function lineasVigentes(): HasManyThrough
    {
        return $this->hasManyThrough(ContratoLinea::class, Contrato::class)
            ->where('contratos.estado', 'activo');
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('estado', 'activo');
    }

    public function getDocumentoCompletoAttribute(): string
    {
        return "{$this->tipo_doc} {$this->documento}";
    }

    /** Etiqueta para selects: "razón · RUC 20…" */
    public function getEtiquetaAttribute(): string
    {
        return "{$this->razon_social} · {$this->documento_completo}";
    }
}
