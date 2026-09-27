<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Support\PestanasEnTabla;
use App\Filament\Support\Ui;
use App\Models\Cliente;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageClientes extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = ClienteResource::class;

    /** Nota bajo la tabla, como el pie del listado en docs/prototype/clientes.html. */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
            fn () => view('filament.nota-tabla', ['texto' => 'Correo, teléfono y dirección se muestran solo en la ficha.']),
            scopes: static::class,
        );
    }

    protected ?string $subheading = 'Empresas y personas con servicios contratados.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo cliente')
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Nuevo cliente')
                ->modalDescription('Los campos con * son obligatorios.')
                ->mutateDataUsing(fn (array $data) => ClienteResource::normalizar($data) + ['estado' => 'activo'])
                ->createAnother(false)
                ->successNotificationTitle('Cliente creado'),
        ];
    }

    public function getTabs(): array
    {
        return Ui::tabsActivos(Cliente::class);
    }
}
