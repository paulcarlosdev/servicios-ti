<?php

namespace App\Support;

use App\Enums\TipoLinea;
use App\Models\ContratoLinea;
use Illuminate\Support\Collection;

/** Líneas con vencimiento de contratos vigentes, ordenadas por fecha (Biz.renovaciones). */
class Renovaciones
{
    /** @return Collection<int, ContratoLinea> */
    public static function lineas(): Collection
    {
        return once(fn () => ContratoLinea::query()
            ->with('contrato.cliente')
            ->whereHas('contrato', fn ($q) => $q->where('estado', 'activo'))
            ->whereNotNull('vence_el')
            ->orderBy('vence_el')
            ->get());
    }

    /** @return Collection<int, ContratoLinea> */
    public static function conAlerta(string $alerta): Collection
    {
        return self::lineas()->filter(fn (ContratoLinea $l) => $l->alerta() === $alerta)->values();
    }

    /** Ingreso mensual recurrente por tipo de línea (S/, IGV incluido). @return array<string, float> */
    public static function mrrPorTipo(): array
    {
        $lineas = ContratoLinea::query()->with('contrato')
            ->whereHas('contrato', fn ($q) => $q->where('estado', 'activo'))->get();

        $porTipo = array_fill_keys(array_map(fn ($t) => $t->value, TipoLinea::cases()), 0.0);
        foreach ($lineas as $l) {
            $porTipo[$l->tipo->value] += $l->mensualPen();
        }

        return $porTipo;
    }
}
