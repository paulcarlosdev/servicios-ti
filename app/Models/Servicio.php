<?php

namespace App\Models;

use App\Enums\Periodo;
use App\Enums\TipoLinea;
use App\Models\Contracts\ItemCatalogo;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan en la nube: VPS o Hosting. */
#[Guarded(['id'])]
class Servicio extends Model implements ItemCatalogo
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoLinea::class,
            'destacado' => 'boolean',
            'precio_mensual' => 'float', 'precio_semestral' => 'float', 'precio_anual' => 'float',
        ];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ContratoLinea::class);
    }

    /** Cantidad contratada en contratos no cancelados. */
    public function uso(): int
    {
        return (int) $this->lineas()->whereHas('contrato', fn ($q) => $q->where('estado', '!=', 'cancelado'))->sum('cantidad');
    }

    public function precios(): array
    {
        $precios = [];
        foreach ([Periodo::Mensual, Periodo::Semestral, Periodo::Anual] as $p) {
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
        return $this->tipo === TipoLinea::Vps
            ? "{$this->cpu} vCPU · {$this->ram} GB RAM · {$this->disco} GB SSD"
            : $this->sitios.' '.($this->sitios == 1 ? 'sitio' : 'sitios')." · {$this->disco} GB";
    }

    /** @return list<string> */
    public function caracteristicas(): array
    {
        return array_values(array_filter(array_map('trim', explode('·', (string) $this->extra))));
    }
}
