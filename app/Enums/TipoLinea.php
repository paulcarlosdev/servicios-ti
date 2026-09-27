<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Origen de una línea de contrato o cotización. */
enum TipoLinea: string implements HasColor, HasIcon, HasLabel
{
    case Dominio = 'dominio';
    case Vps = 'vps';
    case Hosting = 'hosting';
    case Vario = 'vario';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dominio => 'Dominio',
            self::Vps => 'VPS',
            self::Hosting => 'Hosting',
            self::Vario => 'Servicio vario',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dominio => 'sky',
            self::Vps => 'violet',
            self::Hosting => 'emerald',
            self::Vario => 'amber',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Dominio => Heroicon::OutlinedGlobeAlt,
            self::Vps => Heroicon::OutlinedServer,
            self::Hosting => Heroicon::OutlinedCloud,
            self::Vario => Heroicon::OutlinedPuzzlePiece,
        };
    }
}
