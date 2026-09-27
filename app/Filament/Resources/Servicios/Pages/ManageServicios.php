<?php

namespace App\Filament\Resources\Servicios\Pages;

use App\Filament\Resources\Servicios\ServicioResource;
use App\Filament\Support\PestanasEnTabla;
use App\Models\Servicio;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/** Mismo diseño que Clientes: pestañas dentro de la tabla y nota bajo la tarjeta. */
class ManageServicios extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = ServicioResource::class;

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
            fn () => view('filament.nota-tabla', ['texto' => '“—” indica que el periodo no se ofrece. El % de ahorro es frente a pagar mensual.']),
            scopes: static::class,
        );
    }

    protected static ?string $title = 'VPS y Hosting';

    protected ?string $subheading = 'Planes en la nube con sus recursos. El periodo de pago se elige al agregarlos a un contrato.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo plan')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Nuevo plan')
                ->fillForm(fn () => ['tipo' => $this->activeTab === 'hosting' ? 'hosting' : 'vps', 'moneda' => 'USD', 'estado' => true])
                ->createAnother(false)
                ->successNotificationTitle('Plan creado'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'vps' => Tab::make('VPS')
                ->icon(Heroicon::OutlinedServer)
                ->badge(Servicio::where('tipo', 'vps')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', 'vps')),
            'hosting' => Tab::make('Hosting')
                ->icon(Heroicon::OutlinedCloud)
                ->badge(Servicio::where('tipo', 'hosting')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', 'hosting')),
        ];
    }
}
