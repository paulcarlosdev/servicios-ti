<?php

namespace App\Filament\Resources\ServiciosVarios\Pages;

use App\Filament\Resources\ServiciosVarios\ServicioVarioResource;
use App\Filament\Support\PestanasEnTabla;
use App\Filament\Support\Ui;
use App\Models\ServicioVario;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

/** Mismo diseño que docs/prototype/servicios-varios.html: pestañas dentro de la tabla y nota bajo la tarjeta. */
class ManageServiciosVarios extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = ServicioVarioResource::class;

    protected static ?string $title = 'Servicios varios';

    protected ?string $subheading = 'Servicios adicionales como soporte, correo, certificados o migraciones. Ofrece solo los periodos que apliquen.';

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
            fn () => view('filament.nota-tabla', ['texto' => '“—” indica que el periodo no se ofrece.']),
            scopes: static::class,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo servicio')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Nuevo servicio vario')
                ->createAnother(false)
                ->successNotificationTitle('Servicio creado'),
        ];
    }

    public function getTabs(): array
    {
        return Ui::tabsActivos(ServicioVario::class);
    }
}
