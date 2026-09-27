<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Formatos de presentación del prototipo (fmt en assets/app.js). */
class Formato
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public static function num(float|int|string|null $n, int $decimales = 2): string
    {
        return number_format((float) $n, $decimales, '.', ',');
    }

    public static function pen(float|int|string|null $n): string
    {
        return 'S/ '.self::num($n);
    }

    public static function usd(float|int|string|null $n): string
    {
        return '$ '.self::num($n);
    }

    public static function money(float|int|string|null $n, ?string $moneda): string
    {
        return $moneda === 'USD' ? self::usd($n) : self::pen($n);
    }

    /** 5 ene 2026 */
    public static function fecha(CarbonInterface|string|null $fecha): string
    {
        if (blank($fecha)) {
            return '—';
        }
        $f = Carbon::parse($fecha);

        return $f->day.' '.self::MESES[$f->month - 1].' '.$f->year;
    }

    /** Días enteros desde hoy (negativo = pasado). */
    public static function diff(CarbonInterface|string $fecha): int
    {
        return (int) today()->diffInDays(Carbon::parse($fecha)->startOfDay(), false);
    }

    /** hoy, mañana, ayer, en N días, hace N días */
    public static function rel(CarbonInterface|string|null $fecha): string
    {
        if (blank($fecha)) {
            return '';
        }
        $d = self::diff($fecha);

        return match (true) {
            $d === 0 => 'hoy',
            $d === 1 => 'mañana',
            $d === -1 => 'ayer',
            $d > 0 => "en {$d} días",
            default => 'hace '.abs($d).' días',
        };
    }
}
