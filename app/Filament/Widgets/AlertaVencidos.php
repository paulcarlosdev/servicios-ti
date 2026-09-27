<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Support\Renovaciones;
use Filament\Widgets\Widget;

/** Banner rojo cuando hay servicios vencidos sin renovar. */
class AlertaVencidos extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.alerta-vencidos';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Renovaciones::conAlerta('vencido')->isNotEmpty();
    }

    protected function getViewData(): array
    {
        $vencidas = Renovaciones::conAlerta('vencido');

        return [
            'vencidas' => $vencidas,
            'url' => ContratoResource::urlVer($vencidas->first()->contrato_id),
        ];
    }
}
