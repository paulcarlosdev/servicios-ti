<?php

namespace App\Models;

use App\Models\Contracts\ItemCatalogo;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Extensión de dominio (.pe, .com.pe…) con su precio anual. */
#[Guarded(['id'])]
class DominioTipo extends Model implements ItemCatalogo
{
    protected function casts(): array
    {
        return ['precio_usd' => 'float', 'precio_pen' => 'float'];
    }

    public function registros(): HasMany
    {
        return $this->hasMany(DominioRegistro::class);
    }

    /** Líneas de contrato que facturan dominios con esta extensión. */
    public function lineas(): HasMany
    {
        return $this->hasMany(ContratoLinea::class);
    }

    /** El dominio se factura por año al precio en USD. */
    public function precios(): array
    {
        return ['anual' => (float) $this->precio_usd];
    }

    public function monedaLinea(): string
    {
        return 'USD';
    }

    public function nombreLinea(): string
    {
        return "Dominio {$this->extension}";
    }

    public function detalleLinea(): string
    {
        return '';
    }
}
