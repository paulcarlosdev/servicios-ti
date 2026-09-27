<?php

namespace App\Support;

use App\Enums\Periodo;

/**
 * Cálculo de montos de líneas y documentos (Biz en assets/app.js).
 * Una línea es cualquier array/objeto con precio, moneda, cantidad y periodo.
 * No se redondea al calcular; solo al mostrar.
 */
class Montos
{
    /** @return array{usd: float, pen: float} */
    public static function unitarios(float $precio, ?string $moneda, float $tc): array
    {
        $tc = $tc > 0 ? $tc : 1;

        return $moneda === 'USD'
            ? ['usd' => $precio, 'pen' => $precio * $tc]
            : ['usd' => $precio / $tc, 'pen' => $precio];
    }

    /** @return array{usd: float, pen: float} */
    public static function subtotal(array|object $linea, float $tc): array
    {
        $l = (array) (is_object($linea) && method_exists($linea, 'toArray') ? $linea->toArray() : $linea);
        $u = self::unitarios((float) ($l['precio'] ?? 0), $l['moneda'] ?? 'USD', $tc);
        $cantidad = max(1, (int) ($l['cantidad'] ?? 1));

        return ['usd' => $u['usd'] * $cantidad, 'pen' => $u['pen'] * $cantidad];
    }

    /**
     * @param  iterable<array|object>  $lineas
     * @return array{subPen: float, subUsd: float, igvPen: float, igvUsd: float, totalPen: float, totalUsd: float, tasa: float}
     */
    public static function totales(iterable $lineas, float $tc, bool $igv, ?float $tasa = null): array
    {
        $tasa ??= Empresa::igv();
        $k = $igv ? $tasa : 0;
        $subPen = $subUsd = 0.0;
        foreach ($lineas as $l) {
            $s = self::subtotal($l, $tc);
            $subPen += $s['pen'];
            $subUsd += $s['usd'];
        }

        return [
            'subPen' => $subPen, 'subUsd' => $subUsd,
            'igvPen' => $subPen * $k, 'igvUsd' => $subUsd * $k,
            'totalPen' => $subPen * (1 + $k), 'totalUsd' => $subUsd * (1 + $k),
            'tasa' => $tasa,
        ];
    }

    /** Equivalente mensual en soles, sin IGV (0 en pago único). */
    public static function mensual(array|object $linea, float $tc): float
    {
        $periodo = is_object($linea) ? $linea->periodo : ($linea['periodo'] ?? null);
        $periodo = $periodo instanceof Periodo ? $periodo : Periodo::tryFrom((string) $periodo);
        $meses = $periodo?->meses() ?? 0;

        return $meses > 0 ? self::subtotal($linea, $tc)['pen'] / $meses : 0.0;
    }

    /** Ahorro % de un periodo frente a pagar mensual (null si no aplica). */
    public static function ahorro(?float $mensual, ?float $precio, Periodo $periodo): ?int
    {
        if (! $mensual || ! $precio || $periodo->meses() <= 1) {
            return null;
        }
        $a = (int) round((1 - $precio / ($mensual * $periodo->meses())) * 100);

        return $a > 0 ? $a : null;
    }
}
