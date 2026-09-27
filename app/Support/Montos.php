<?php

namespace App\Support;

use App\Enums\Periodo;

/**
 * Cálculo de montos de líneas y documentos (Biz en assets/app.js).
 * Una línea es cualquier array/objeto con precio, moneda, cantidad y periodo.
 * Los precios de línea son finales (incluyen IGV si el documento lo aplica). No se redondea al calcular; solo al mostrar.
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
     * Totales del documento. Los precios de las líneas son FINALES (IGV incluido cuando el documento
     * aplica IGV): el total es la suma de subtotales y la base imponible y el IGV se desglosan hacia atrás.
     *
     * @param  iterable<array|object>  $lineas
     * @return array{subPen: float, subUsd: float, igvPen: float, igvUsd: float, totalPen: float, totalUsd: float, tasa: float}
     */
    public static function totales(iterable $lineas, float $tc, bool $igv, ?float $tasa = null): array
    {
        $tasa ??= Empresa::igv();
        $k = self::factorIgv($igv, $tasa);
        $totalPen = $totalUsd = 0.0;
        foreach ($lineas as $l) {
            $s = self::subtotal($l, $tc);
            $totalPen += $s['pen'];
            $totalUsd += $s['usd'];
        }
        $subPen = $totalPen / $k;
        $subUsd = $totalUsd / $k;

        return [
            'subPen' => $subPen, 'subUsd' => $subUsd,
            'igvPen' => $totalPen - $subPen, 'igvUsd' => $totalUsd - $subUsd,
            'totalPen' => $totalPen, 'totalUsd' => $totalUsd,
            'tasa' => $tasa,
        ];
    }

    /** Factor IGV: 1.18 si el documento aplica IGV, 1 si no. */
    public static function factorIgv(bool $igv, ?float $tasa = null): float
    {
        return $igv ? 1 + ($tasa ?? Empresa::igv()) : 1.0;
    }

    /** Parte de un monto final (con IGV) que corresponde a la base imponible. */
    public static function sinIgv(float $monto, bool $igv, ?float $tasa = null): float
    {
        return $monto / self::factorIgv($igv, $tasa);
    }

    /** Equivalente mensual en soles, IGV incluido (0 en pago único). */
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
