<?php

namespace App\Filament\Widgets;

use App\Enums\Rol;
use App\Models\User;
use Filament\Widgets\Widget;

/** Matriz estática de permisos por rol (la misma que aplican las políticas). */
class PermisosPorRol extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.permisos-por-rol';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'roles' => Rol::cases(),
            'modulos' => [
                'clientes' => 'Clientes',
                'documentos' => 'Cotizaciones y contratos',
                'catalogo' => 'Catálogo',
                'montos' => 'Montos y PDF',
                'empresa' => 'Mi empresa',
                'usuarios' => 'Usuarios',
            ],
            'permisos' => User::PERMISOS,
        ];
    }
}
