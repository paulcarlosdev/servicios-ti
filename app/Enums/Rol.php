<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Rol: string implements HasColor, HasDescription, HasLabel
{
    case Admin = 'admin';
    case Gerente = 'gerente';
    case Tecnico = 'tecnico';
    case Auditor = 'auditor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Gerente => 'Gerente',
            self::Tecnico => 'Técnico',
            self::Auditor => 'Auditor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'primary',
            self::Gerente => 'info',
            default => 'gray',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Admin => 'Acceso total, incluida la gestión de usuarios y la ficha de la empresa.',
            self::Gerente => 'Gestiona clientes, cotizaciones, contratos y catálogo.',
            self::Tecnico => 'Consulta contratos y servicios para aprovisionar. No ve montos.',
            self::Auditor => 'Solo lectura de todos los módulos.',
        };
    }
}
