<?php

namespace App\Models;

use App\Enums\Periodo;
use App\Models\Contracts\ItemCatalogo;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('servicios_varios')]
#[Guarded(['id'])]
class ServicioVario extends Model implements ItemCatalogo
{
    protected function casts(): array
    {
        return [
            'precio_mensual' => 'float', 'precio_semestral' => 'float',
            'precio_anual' => 'float', 'precio_unico' => 'float',
        ];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ContratoLinea::class);
    }

    public function lineasEnUso(): HasMany
    {
        return $this->lineas()->whereHas('contrato', fn ($q) => $q->where('estado', '!=', 'cancelado'));
    }

    public function precios(): array
    {
        $precios = [];
        foreach (Periodo::cases() as $p) {
            if (($v = $this->{$p->columnaPrecio()}) > 0) {
                $precios[$p->value] = (float) $v;
            }
        }

        return $precios;
    }

    public function monedaLinea(): string
    {
        return $this->moneda;
    }

    public function nombreLinea(): string
    {
        return $this->nombre;
    }

    public function detalleLinea(): string
    {
        return '';
    }
}
