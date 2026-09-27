<?php

namespace App\Models\Concerns;

use App\Support\Montos;
use Illuminate\Support\Str;

/** Numeración PREF-AAAA-NNNN, UUID público y totales de contratos y cotizaciones. */
trait EsDocumento
{
    abstract public static function prefijo(): string;

    protected static function bootEsDocumento(): void
    {
        static::creating(function (self $doc) {
            $doc->uuid ??= (string) Str::uuid();
            $doc->numero ??= static::siguienteNumero();
        });
    }

    /** CON-2026-0009: correlativo sobre todos los años (como el prototipo), año actual en el prefijo. */
    public static function siguienteNumero(): string
    {
        $max = static::query()->pluck('numero')
            ->map(fn ($n) => (int) Str::afterLast($n, '-'))
            ->max() ?? 0;

        return static::prefijo().'-'.now()->year.'-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array{subPen: float, subUsd: float, igvPen: float, igvUsd: float, totalPen: float, totalUsd: float, tasa: float} */
    public function totales(): array
    {
        return Montos::totales(
            $this->lineas->map->only(['precio', 'moneda', 'cantidad']),
            (float) $this->tipo_cambio,
            (bool) $this->aplica_igv,
        );
    }

    /** Guarda los totales calculados (con IGV si aplica). */
    public function recalcularTotales(): void
    {
        $this->load('lineas');
        $t = $this->totales();
        $this->forceFill(['total_pen' => round($t['totalPen'], 2), 'total_usd' => round($t['totalUsd'], 2)])->saveQuietly();
    }

    /** ¿Alguna línea muestra USD? Si todas lo ocultan, el PDF tampoco muestra el total en USD. */
    public function algunUsd(): bool
    {
        return $this->lineas->contains(fn ($l) => ! $l->ocultar_usd);
    }
}
