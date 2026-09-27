<?php

namespace App\Filament\Resources\Cotizaciones\Pages;

use App\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Filament\Support\PestanasEnTabla;
use App\Models\Cotizacion;
use App\Support\Empresa;
use App\Support\Plantillas;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** Cabecera mínima como Contratos: título, descripción y acción; los conteos están en las pestañas de la tabla. */
class ManageCotizaciones extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = CotizacionResource::class;

    public function getSubheading(): string
    {
        return 'Propuestas válidas por '.Empresa::validez().' días. Una cotización aceptada se convierte en contrato con un clic.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva cotización')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::SevenExtraLarge)
                ->modalHeading('Nueva cotización')
                ->modalDescription(fn () => 'Se numerará como '.Cotizacion::siguienteNumero().'.')
                ->fillForm(fn (array $arguments) => [
                    'cliente_id' => $arguments['cliente'] ?? null,
                    'fecha' => today()->toDateString(),
                    'valida_hasta' => today()->addDays(Empresa::validez())->toDateString(),
                    'tipo_cambio' => Empresa::tc(),
                    'aplica_igv' => true,
                    'condiciones_html' => Plantillas::CONDICIONES,
                ])
                ->mutateDataUsing(fn (array $data) => $data + ['estado' => 'pendiente'])
                ->modalSubmitActionLabel('Guardar')
                ->extraModalFooterActions(fn (CreateAction $action) => [
                    $action->makeModalSubmitAction('verPdf', ['verPdf' => true])
                        ->label('Guardar y ver PDF')
                        ->icon(Heroicon::OutlinedDocumentText),
                ])
                ->createAnother(false)
                ->successNotification(null)
                ->after(function (Cotizacion $record, array $arguments) {
                    CotizacionResource::despuesDeGuardar($record, 'Cotización creada');
                    if ($arguments['verPdf'] ?? false) {
                        $this->replaceMountedAction('verPdf', [], ['table' => true, 'recordKey' => $record->getKey()]);
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['todas' => Tab::make('Todas')->badge(Cotizacion::count())];
        foreach (['pendiente' => 'Pendientes', 'aceptada' => 'Aceptadas', 'convertida' => 'Convertidas', 'rechazada' => 'Rechazadas', 'expirada' => 'Expiradas'] as $estado => $label) {
            $tabs[$estado] = Tab::make($label)
                ->badge(Cotizacion::enEstado($estado)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->enEstado($estado));
        }

        return $tabs;
    }
}
