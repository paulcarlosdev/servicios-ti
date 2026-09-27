<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Filament\Widgets\AlertaVencidos;
use App\Filament\Widgets\IngresoPorTipo;
use App\Filament\Widgets\PorCerrar;
use App\Filament\Widgets\ProximasRenovaciones;
use App\Filament\Widgets\ResumenPanel;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/** Panel de control (HU-013): resumen operativo de contratos, renovaciones y cotizaciones. */
class Panel extends Dashboard
{
    protected static ?string $title = 'Panel';

    protected static ?string $navigationLabel = 'Panel';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public function getSubheading(): string
    {
        return ucfirst(now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevaCotizacion')
                ->label('Nueva cotización')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->url(CotizacionResource::getUrl('index', ['action' => 'create']))
                ->visible(fn () => auth()->user()->puedeEditar('documentos')),
            Action::make('nuevoContrato')
                ->label('Nuevo contrato')
                ->icon(Heroicon::OutlinedPlus)
                ->url(ContratoResource::getUrl('index', ['action' => 'create']))
                ->visible(fn () => auth()->user()->puedeEditar('documentos')),
        ];
    }

    public function getWidgets(): array
    {
        return [
            AlertaVencidos::class,
            ResumenPanel::class,
            ProximasRenovaciones::class,
            PorCerrar::class,
            IngresoPorTipo::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
