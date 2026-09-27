<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Support\Renovaciones;
use Filament\Widgets\Widget;

/** Primeras 7 renovaciones: vencidas, luego por vencer, luego al día. */
class ProximasRenovaciones extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.proximas-renovaciones';

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 2];

    protected function getViewData(): array
    {
        $orden = ['vencido' => 0, 'por_vencer' => 1, 'ok' => 2];

        return [
            'lineas' => Renovaciones::lineas()->sortBy(fn ($l) => [$orden[$l->alerta()], $l->vence_el])->take(7),
            'urlContratos' => ContratoResource::getUrl('index'),
            'verMontos' => auth()->user()->puedeVer('montos'),
        ];
    }
}
