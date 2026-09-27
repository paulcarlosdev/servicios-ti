<?php

namespace App\Filament\Resources\Cotizaciones;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Cotizaciones\Pages\ManageCotizaciones;
use App\Filament\Support\AccionesDocumento;
use App\Filament\Support\EditorLineas;
use App\Filament\Support\Ui;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Support\Empresa;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Cotizaciones (HU-012, HU-016): válidas N días; una aceptada se convierte en contrato. */
class CotizacionResource extends Resource
{
    protected static ?string $model = Cotizacion::class;

    protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones';

    protected static ?string $slug = 'cotizaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'numero';

    public static function getNavigationBadge(): ?string
    {
        $n = Cotizacion::enEstado('pendiente')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Group::make([
                    Section::make('Cliente y validez')
                        ->columns(4)
                        ->schema([
                            ContratoResource::selectCliente()->columnSpan(4),
                            DatePicker::make('fecha')
                                ->label('Fecha de emisión')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, $old, Get $get, Set $set) {
                                    ContratoResource::moverInicioLineas($state, $old, $get, $set);
                                    // Si la validez seguía siendo la de por defecto, se mueve con la emisión
                                    $dias = Empresa::validez();
                                    if ($state && $old && $get('valida_hasta') && Carbon::parse($get('valida_hasta'))->isSameDay(Carbon::parse($old)->addDays($dias))) {
                                        $set('valida_hasta', Carbon::parse($state)->addDays($dias)->toDateString());
                                    }
                                })
                                ->columnSpan(2),
                            DatePicker::make('valida_hasta')
                                ->label('Válida hasta')
                                ->required()
                                ->afterOrEqual('fecha')
                                ->helperText(fn () => Empresa::validez().' días desde la emisión')
                                ->validationMessages(['after_or_equal' => 'Debe ser posterior a la emisión.'])
                                ->columnSpan(2),
                            ContratoResource::campoTipoCambio()->label('Tipo de cambio')->columnSpan(2),
                            ContratoResource::toggleIgv()->columnSpan(2),
                        ]),
                    Section::make('Servicios')
                        ->schema([EditorLineas::repeater('fecha')->minItems(1)->validationMessages(['min' => 'Agrega al menos un servicio.'])]),
                    Section::make('Condiciones')
                        ->schema([
                            RichEditor::make('condiciones_html')
                                ->hiddenLabel()
                                ->helperText('Se heredan al convertir la cotización en contrato.')
                                ->toolbarButtons([['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                                ->dehydrateStateUsing(fn (?string $state) => $state ? Str::sanitizeHtml($state) : null),
                        ]),
                ])->columnSpan(['lg' => 2]),
                Group::make([
                    Section::make('Resumen')->schema([EditorLineas::resumen('fecha')]),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.cotizaciones.detalle')->viewData(fn (Cotizacion $record) => ['cotizacion' => $record->load(['cliente', 'lineas'])]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cliente', 'lineas']))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Cliente, N.º o servicio…')
            ->columns([
                ViewColumn::make('cliente')
                    ->label('Cliente')
                    ->view('filament.cotizaciones.columna-cliente')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('numero', 'like', "%{$search}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'like', "%{$search}%"))
                        ->orWhereHas('lineas', fn ($l) => $l->where('identificador', 'like', "%{$search}%")->orWhere('nombre', 'like', "%{$search}%"))),
                ViewColumn::make('servicios')->label('Servicios')->view('filament.documentos.columna-lineas'),
                TextColumn::make('valida_hasta')
                    ->label('Validez')
                    ->formatStateUsing(fn ($state) => Formato::fecha($state))
                    ->description(fn (Cotizacion $r) => $r->pendiente() ? 'vence '.Formato::rel($r->valida_hasta) : 'Validez')
                    ->color(fn (Cotizacion $r) => $r->pendiente() && Formato::diff($r->valida_hasta) <= 5 ? 'warning' : null)
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('total_pen')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Formato::pen($state))
                    ->description(fn (Cotizacion $r) => Formato::usd($r->total_usd))
                    ->sortable()
                    ->visible(fn () => auth()->user()->puedeVer('montos')),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Cotizacion $r) => $r->estadoVisible())
                    ->formatStateUsing(fn ($state) => Ui::estado($state)->getLabel())
                    ->color(fn ($state) => Ui::estado($state)->getColor())
                    ->icon(fn ($state) => Ui::estado($state)->getIcon()),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->hiddenLabel()
                    ->tooltip('Ver')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn (Cotizacion $r) => $r->numero)
                    ->modalDescription(fn (Cotizacion $r) => $r->cliente->razon_social.' · emitida '.Formato::fecha($r->fecha))
                    ->modalIcon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraModalFooterActions(fn (Cotizacion $r) => [
                        AccionesDocumento::verPdf('pdfDesdeVer')->label('PDF'),
                        self::accionCambiarEstado('rechazada'),
                        self::accionCambiarEstado('aceptada'),
                        self::accionConvertir(),
                        Action::make('verContrato')->label('Ver contrato')->icon(Heroicon::OutlinedDocumentText)
                            ->visible($r->estado === 'convertida' && $r->contrato_id)
                            ->url(fn () => ContratoResource::urlVer($r->contrato_id)),
                        self::accionDuplicar()->visible(in_array($r->estadoVisible()->value, ['expirada', 'rechazada'])),
                    ]),
                ActionGroup::make([
                    ActionGroup::make([
                        AccionesDocumento::verPdf(),
                        self::accionCopiarEnlace(),
                        AccionesDocumento::enviarCorreo(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        EditAction::make()
                            ->visible(fn (Cotizacion $r) => $r->pendiente() && auth()->user()->puedeEditar('documentos'))
                            ->modalWidth(Width::SevenExtraLarge)
                            ->modalHeading(fn (Cotizacion $r) => "Editar {$r->numero}")
                            ->modalDescription(fn (Cotizacion $r) => $r->cliente->razon_social)
                            ->successNotification(null)
                            ->after(fn (Cotizacion $record) => self::despuesDeGuardar($record, 'Cotización actualizada')),
                        self::accionCambiarEstado('aceptada'),
                        self::accionConvertir(),
                        self::accionDuplicar(),
                    ])->dropdown(false),
                    self::accionCambiarEstado('rechazada'),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('Aún no hay cotizaciones')
            ->emptyStateDescription('Crea una cotización para enviarla al cliente.');
    }

    public static function despuesDeGuardar(Cotizacion $cotizacion, string $titulo): void
    {
        $cotizacion->recalcularTotales();
        Notification::make()->title($titulo)->body($cotizacion->numero)->success()->send();
    }

    /** Pendiente → aceptada o rechazada, sin confirmación. */
    public static function accionCambiarEstado(string $estado): Action
    {
        $acepta = $estado === 'aceptada';

        return Action::make($acepta ? 'marcarAceptada' : 'marcarRechazada')
            ->label($acepta ? 'Marcar aceptada' : 'Marcar rechazada')
            ->icon($acepta ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
            ->color($acepta ? 'success' : 'danger')
            ->visible(fn (Cotizacion $record) => $record->pendiente() && auth()->user()->puedeEditar('documentos'))
            ->action(function (Cotizacion $record) use ($estado, $acepta) {
                $record->update(['estado' => $estado]);
                Notification::make()
                    ->title($acepta ? 'Cotización aceptada' : 'Cotización rechazada')
                    ->body($record->numero)
                    ->{$acepta ? 'success' : 'info'}()
                    ->send();
            });
    }

    /** Crea un contrato borrador con cliente, líneas, precios, tipo de cambio y condiciones. */
    public static function accionConvertir(): Action
    {
        return Action::make('convertir')
            ->label('Convertir en contrato')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->visible(fn (Cotizacion $record) => $record->estado === 'aceptada' && auth()->user()->puedeEditar('documentos'))
            ->modalHeading('Convertir en contrato')
            ->modalDescription(fn (Cotizacion $record) => "{$record->numero} → ".Contrato::siguienteNumero())
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Crear contrato')
            ->modalCancelActionLabel('Ahora no')
            ->fillForm(['fecha_inicio' => today()->toDateString()])
            ->schema(fn (Cotizacion $record) => [
                Text::make("El contrato hereda el cliente, los {$record->lineas->count()} servicios, precios, tipo de cambio y condiciones. Se crea como borrador para que lo revises y generes el PDF."),
                DatePicker::make('fecha_inicio')
                    ->label('Fecha de inicio del contrato')
                    ->required()
                    ->helperText('Desde aquí se calculan las renovaciones.')
                    ->validationMessages(['required' => 'Indica la fecha de inicio.']),
            ])
            ->action(function (Cotizacion $record, array $data) {
                $contrato = DB::transaction(function () use ($record, $data) {
                    $contrato = Contrato::create([
                        'cliente_id' => $record->cliente_id,
                        'cotizacion_id' => $record->id,
                        'fecha_inicio' => $data['fecha_inicio'],
                        'tipo_cambio' => $record->tipo_cambio,
                        'aplica_igv' => $record->aplica_igv,
                        'estado' => 'borrador',
                        'condiciones_html' => $record->condiciones_html,
                    ]);
                    foreach ($record->lineas as $l) {
                        $contrato->lineas()->create($l->replicate(['cotizacion_id', 'vence_el'])->fill(['fecha_inicio' => $data['fecha_inicio']])->toArray());
                    }
                    $contrato->recalcularTotales();
                    $contrato->registrar("Borrador creado desde {$record->numero}");
                    $record->update(['estado' => 'convertida', 'contrato_id' => $contrato->id]);

                    return $contrato;
                });
                Notification::make()->title('Contrato creado')->body("{$contrato->numero} quedó como borrador.")->success()->send();

                return redirect(ContratoResource::urlVer($contrato->id));
            });
    }

    public static function accionDuplicar(): Action
    {
        return Action::make('duplicar')
            ->label('Duplicar')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->visible(fn () => auth()->user()->puedeEditar('documentos'))
            ->action(function (Cotizacion $record, $livewire) {
                $copia = DB::transaction(function () use ($record) {
                    $copia = $record->replicate(['uuid', 'numero', 'contrato_id']);
                    $copia->fill([
                        'fecha' => today(),
                        'valida_hasta' => today()->addDays(Empresa::validez()),
                        'estado' => 'pendiente',
                        'tipo_cambio' => Empresa::tc(),
                    ])->save();
                    foreach ($record->lineas as $l) {
                        $copia->lineas()->create($l->replicate(['cotizacion_id', 'vence_el', 'fecha_inicio'])->toArray());
                    }
                    $copia->recalcularTotales();

                    return $copia;
                });
                Notification::make()->title('Cotización duplicada')->body("{$copia->numero} con validez renovada.")->success()->send();
                $livewire->replaceMountedAction('edit', [], ['table' => true, 'recordKey' => $copia->getKey()]);
            });
    }

    public static function accionCopiarEnlace(): Action
    {
        return Action::make('copiarEnlace')
            ->label('Copiar enlace público')
            ->icon(Heroicon::OutlinedLink)
            ->alpineClickHandler(fn (Cotizacion $record) => 'window.navigator.clipboard.writeText('.\Illuminate\Support\Js::from($record->urlPublica()).');'
                .' $tooltip('.\Illuminate\Support\Js::from('Enlace copiado').', { timeout: 1500 })');
    }

    public static function urlVer(int $id): string
    {
        return static::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $id]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCotizaciones::route('/'),
        ];
    }
}
