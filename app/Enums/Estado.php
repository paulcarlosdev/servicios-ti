<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Badges de estado comunes a todas las pantallas (mismo texto y color en todo el panel). */
enum Estado: string implements HasColor, HasIcon, HasLabel
{
    case Activo = 'activo';
    case PorVencer = 'por_vencer';
    case Vencido = 'vencido';
    case Borrador = 'borrador';
    case Cancelado = 'cancelado';
    case Inactivo = 'inactivo';
    case Pendiente = 'pendiente';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Convertida = 'convertida';
    case Expirada = 'expirada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::PorVencer => 'Por vencer',
            self::Vencido => 'Vencido',
            self::Borrador => 'Borrador',
            self::Cancelado => 'Cancelado',
            self::Inactivo => 'Inactivo',
            self::Pendiente => 'Pendiente',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
            self::Convertida => 'Convertida',
            self::Expirada => 'Expirada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo, self::Aceptada => 'success',
            self::PorVencer, self::Pendiente => 'warning',
            self::Vencido, self::Rechazada => 'danger',
            self::Convertida => 'info',
            default => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Activo, self::Aceptada => Heroicon::OutlinedCheckCircle,
            self::PorVencer, self::Pendiente, self::Expirada => Heroicon::OutlinedClock,
            self::Vencido => Heroicon::OutlinedExclamationCircle,
            self::Borrador => Heroicon::OutlinedPencilSquare,
            self::Cancelado, self::Inactivo => Heroicon::OutlinedNoSymbol,
            self::Rechazada => Heroicon::OutlinedXCircle,
            self::Convertida => Heroicon::OutlinedDocumentCheck,
        };
    }
}
