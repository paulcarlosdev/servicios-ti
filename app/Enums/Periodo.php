<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Periodo de pago de una línea (SPEC 4.2): se renueva cada N meses; el pago único no vence. */
enum Periodo: string implements HasLabel
{
    case Mensual = 'mensual';
    case Semestral = 'semestral';
    case Anual = 'anual';
    case Unico = 'unico';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
            self::Unico => 'Pago único',
        };
    }

    public function meses(): int
    {
        return match ($this) {
            self::Mensual => 1,
            self::Semestral => 6,
            self::Anual => 12,
            self::Unico => 0,
        };
    }

    public function sufijo(): string
    {
        return match ($this) {
            self::Mensual => '/mes',
            self::Semestral => '/6 meses',
            self::Anual => '/año',
            self::Unico => '',
        };
    }

    /** Días antes del vencimiento en que la línea pasa a "por vencer". */
    public function umbralAviso(): int
    {
        return $this === self::Mensual ? 7 : 30;
    }

    /** Columna de precio en los catálogos (servicios y servicios varios). */
    public function columnaPrecio(): string
    {
        return 'precio_'.$this->value;
    }
}
