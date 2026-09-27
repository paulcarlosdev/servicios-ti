<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Support\Formato;
use App\Support\Renovaciones;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenPanel extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $vencidos = Renovaciones::conAlerta('vencido')->count();
        $url = ContratoResource::getUrl('index');

        $stats = [
            Stat::make('Contratos vigentes', Contrato::where('estado', 'activo')->count())
                ->description(Cliente::activos()->count().' clientes activos')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->url($url),
            Stat::make('Servicios por renovar', Renovaciones::conAlerta('por_vencer')->count())
                ->description('Próximos 30 días')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url(ContratoResource::getUrl('index', ['tab' => 'por_vencer'])),
            Stat::make('Servicios vencidos', $vencidos)
                ->description($vencidos > 0 ? 'Requieren acción' : 'Todo al día')
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->color($vencidos > 0 ? 'danger' : 'success')
                ->url(ContratoResource::getUrl('index', ['tab' => 'vencido'])),
        ];

        if (auth()->user()->puedeVer('montos')) {
            $mrr = array_sum(Renovaciones::mrrPorTipo());
            $stats[] = Stat::make('Ingreso mensual recurrente', Formato::pen($mrr))
                ->description('IGV incluido · equivalente mensual')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('primary');
        }

        return $stats;
    }
}
