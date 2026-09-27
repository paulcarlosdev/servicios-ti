<?php

namespace App\Filament\Resources\Dominios\Pages;

use App\Filament\Resources\Dominios\DominioTipoResource;
use App\Filament\Support\PestanasEnTabla;
use App\Filament\Support\Ui;
use App\Models\DominioTipo;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

/** Mismo diseño que docs/prototype/dominios.html: solo precios de extensiones. */
class ManageDominios extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = DominioTipoResource::class;

    protected static ?string $title = 'Dominios';

    protected ?string $subheading = 'Extensiones disponibles y su precio anual. El nombre del dominio se indica al agregarlo a una cotización o contrato.';

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
            fn () => view('filament.nota-tabla', ['texto' => 'Precios por año. Se copian a la línea al agregar un dominio a una cotización o contrato.']),
            scopes: static::class,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva extensión')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::Large)
                ->modalHeading('Nueva extensión')
                ->modalSubmitActionLabel('Crear extensión')
                ->createAnother(false)
                ->successNotificationTitle('Extensión creada'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = Ui::tabsActivos(DominioTipo::class, 'Todas');
        $tabs['activos']->label('Activas');
        $tabs['inactivos']->label('Inactivas');

        return $tabs;
    }
}
