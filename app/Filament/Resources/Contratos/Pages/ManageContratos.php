<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Support\PestanasEnTabla;
use App\Models\Contrato;
use App\Support\Empresa;
use App\Support\Plantillas;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** Cabecera mínima (docs/prototype/contratos.html): título, descripción y acción; los conteos están en las pestañas de la tabla. */
class ManageContratos extends ManageRecords
{
    use PestanasEnTabla;

    protected static string $resource = ContratoResource::class;

    protected ?string $subheading = 'Servicios contratados por cliente. Cada servicio tiene su propio periodo y fecha de renovación.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo contrato')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::SevenExtraLarge)
                ->modalHeading('Nuevo contrato')
                ->modalDescription(fn () => 'Se numerará como '.Contrato::siguienteNumero().'. Al guardar se genera el PDF.')
                ->fillForm(fn (array $arguments) => [
                    'cliente_id' => $arguments['cliente'] ?? null,
                    'fecha_inicio' => today()->toDateString(),
                    'tipo_cambio' => Empresa::tc(),
                    'aplica_igv' => true,
                    'condiciones_html' => Plantillas::CONDICIONES,
                ])
                ->mutateDataUsing(fn (array $data) => $data + ['estado' => 'borrador'])
                ->modalSubmitActionLabel('Guardar borrador')
                ->extraModalFooterActions(fn (CreateAction $action) => [
                    $action->makeModalSubmitAction('activar', ['activar' => true])
                        ->label('Guardar y generar PDF')
                        ->icon(Heroicon::OutlinedDocumentCheck),
                ])
                ->createAnother(false)
                ->successNotification(null)
                ->after(fn (Contrato $record, array $arguments) => ContratoResource::despuesDeGuardar($record, activar: $arguments['activar'] ?? false, creado: true)),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['todos' => Tab::make('Todos')->badge(Contrato::count())];
        foreach (['activo' => 'Activos', 'por_vencer' => 'Por vencer', 'vencido' => 'Vencidos', 'borrador' => 'Borradores', 'cancelado' => 'Cancelados'] as $estado => $label) {
            $tabs[$estado] = Tab::make($label)
                ->badge(Contrato::enEstado($estado)->count())
                ->badgeColor(match ($estado) {
                    'vencido' => 'danger',
                    'por_vencer' => 'warning',
                    'activo' => 'success',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query) => $query->enEstado($estado));
        }

        return $tabs;
    }
}
