<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Models\Contrato;
use App\Models\Cotizacion;
use Filament\Widgets\Widget;

/** Cotizaciones pendientes y contratos en borrador. */
class PorCerrar extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.por-cerrar';

    protected function getViewData(): array
    {
        return [
            'cotizaciones' => Cotizacion::with('cliente')->enEstado('pendiente')->orderBy('valida_hasta')->get(),
            'borradores' => Contrato::with('cliente')->where('estado', 'borrador')->latest('id')->get(),
            'urlCotizaciones' => CotizacionResource::getUrl('index'),
            'verMontos' => auth()->user()->puedeVer('montos'),
        ];
    }
}
