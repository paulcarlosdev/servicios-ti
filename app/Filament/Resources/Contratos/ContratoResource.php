<?php

namespace App\Filament\Resources\Contratos;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Contratos\Pages\ManageContratos;
use App\Filament\Support\AccionesDocumento;
use App\Filament\Support\EditorLineas;
use App\Filament\Support\Ui;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoLinea;
use App\Services\DocumentoPdf;
use App\Support\Empresa;
use App\Support\Formato;
use App\Support\Montos;
use App\Support\Plantillas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Contratos (HU-005 a HU-010): líneas mixtas, renovación por línea, evidencia firmada y PDF. */
class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static ?string $modelLabel = 'contrato';

    protected static ?string $pluralModelLabel = 'contratos';

    protected static string|\UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'numero';

    public static function getNavigationBadge(): ?string
    {
        // Contratos que requieren atención: por vencer + vencidos (como el menú del prototipo)
        $n = Contrato::enEstado('por_vencer')->count() + Contrato::enEstado('vencido')->count();

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
                    Section::make('1. Cliente y vigencia')
                        ->columns(4)
                        ->schema([
                            self::selectCliente()->columnSpan(4),
                            DatePicker::make('fecha_inicio')
                                ->label('Fecha de inicio')
                                ->required()
                                ->live()
                                ->validationMessages(['required' => 'Indica la fecha de inicio.'])
                                // Las líneas que empezaban con el documento se mueven con él
                                ->afterStateUpdated(fn ($state, $old, Get $get, Set $set) => self::moverInicioLineas($state, $old, $get, $set))
                                ->columnSpan(2),
                            self::campoTipoCambio()->columnSpan(2),
                            self::toggleIgv()->columnSpan(4),
                        ]),
                    Section::make('2. Servicios')
                        ->description('Cada servicio lleva su propio periodo y fecha de renovación.')
                        ->schema([EditorLineas::repeater('fecha_inicio')]),
                    Section::make('3. Condiciones')
                        ->schema([
                            RichEditor::make('condiciones_html')
                                ->hiddenLabel()
                                ->helperText('Se imprimen en el PDF antes de las firmas.')
                                ->toolbarButtons([['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                                ->dehydrateStateUsing(fn (?string $state) => $state ? Str::sanitizeHtml($state) : null)
                                ->hintAction(Action::make('plantilla')
                                    ->label('Usar plantilla')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(fn (Set $set) => $set('condiciones_html', Plantillas::CONDICIONES))),
                        ]),
                    Section::make('4. Documento firmado')
                        ->description('Opcional')
                        ->schema([
                            FileUpload::make('documento_evidencia_path')
                                ->hiddenLabel()
                                ->disk('local')
                                ->directory('evidencias')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(5120)
                                ->storeFileNamesIn('documento_evidencia_nombre')
                                ->helperText('Solo PDF, máximo 5 MB. Puedes subirlo después desde el detalle.')
                                ->validationMessages([
                                    'mimetypes' => 'El documento firmado debe ser PDF.',
                                    'max' => 'El archivo supera los 5 MB.',
                                ]),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Resumen')->schema([
                        Html::make(fn (Get $get) => ($c = Cliente::find($get('cliente_id')))
                            ? '<div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><p class="font-medium text-gray-950 dark:text-white">'.e($c->razon_social).'</p><p class="text-xs text-gray-500">'.e($c->email).'</p></div>'
                            : ''),
                        EditorLineas::resumen('fecha_inicio'),
                    ]),
                    Section::make('Antes de guardar')->schema([
                        Html::make(fn (Get $get) => view('filament.documentos.checklist', ['items' => [
                            ['Cliente seleccionado', filled($get('cliente_id'))],
                            ['Al menos un servicio', filled($get('lineas'))],
                            ['Tipo de cambio válido', (float) $get('tipo_cambio') > 0],
                            ['Documento firmado (opcional)', filled($get('documento_evidencia_path'))],
                        ]])->render()),
                    ]),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function selectCliente(): Select
    {
        return Select::make('cliente_id')
            ->label('Cliente')
            ->relationship('cliente', 'razon_social', fn (Builder $query, $record) => $query
                ->where(fn ($q) => $q->where('estado', 'activo')->orWhere('id', $record?->cliente_id))
                ->orderBy('razon_social'))
            ->getOptionLabelFromRecordUsing(fn (Cliente $c) => $c->etiqueta)
            ->searchable(['razon_social', 'documento'])
            ->preload()
            ->required()
            ->live()
            ->placeholder('Selecciona un cliente…')
            ->validationMessages(['required' => 'Selecciona el cliente.'])
            ->createOptionForm(fn (Schema $schema) => $schema->columns(6)->components(ClienteResource::campos()))
            ->createOptionModalHeading('Nuevo cliente')
            ->createOptionUsing(fn (array $data) => Cliente::create(ClienteResource::normalizar($data) + ['estado' => 'activo'])->getKey());
    }

    public static function campoTipoCambio(): TextInput
    {
        return TextInput::make('tipo_cambio')
            ->label('Tipo de cambio USD→PEN')
            ->numeric()
            ->step(0.001)
            ->prefix('S/')
            ->required()
            ->gt(0)
            ->live(onBlur: true)
            ->helperText(fn () => 'Vigente hoy: '.Formato::num(Empresa::tc(), 3))
            ->validationMessages(['gt' => 'El tipo de cambio debe ser mayor a 0.']);
    }

    public static function toggleIgv(): Toggle
    {
        return Toggle::make('aplica_igv')
            ->label(fn () => 'Aplicar IGV '.round(Empresa::igv() * 100).'%')
            ->default(true)
            ->live();
    }

    public static function moverInicioLineas($nuevo, $viejo, Get $get, Set $set): void
    {
        if (! $nuevo || ! $viejo) {
            return;
        }
        $lineas = collect($get('lineas') ?? [])->map(function ($l) use ($nuevo, $viejo) {
            if (($l['fecha_inicio'] ?? null) && Carbon::parse($l['fecha_inicio'])->isSameDay($viejo)) {
                $l['fecha_inicio'] = $nuevo;
            }

            return $l;
        });
        $set('lineas', $lineas->all());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.contratos.detalle')->viewData(fn (Contrato $record) => ['contrato' => $record->load(['cliente', 'lineas', 'historial.user'])]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cliente', 'lineas']))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Cliente, RUC, N.º o dominio…')
            ->columns([
                TextColumn::make('numero')
                    ->label('Contrato')
                    ->weight('medium')
                    ->description(fn (Contrato $r) => 'Inicio '.Formato::fecha($r->fecha_inicio))
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('numero', 'like', "%{$search}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%"))
                        ->orWhereHas('lineas', fn ($l) => $l->where('identificador', 'like', "%{$search}%")->orWhere('nombre', 'like', "%{$search}%"))),
                ViewColumn::make('cliente')->label('Cliente')->view('filament.documentos.columna-cliente'),
                ViewColumn::make('servicios')->label('Servicios')->view('filament.documentos.columna-lineas'),
                TextColumn::make('proxima_renovacion')
                    ->label('Próx. renovación')
                    ->state(fn (Contrato $r) => $r->proximaRenovacion())
                    ->formatStateUsing(fn ($state) => Formato::fecha($state))
                    // La fecha va en color normal; el "en N días / hace N días" se colorea según el estado
                    ->description(function (Contrato $r) {
                        if (! $prox = $r->proximaRenovacion()) {
                            return null;
                        }
                        $clase = match ($r->estadoVisible()->value) {
                            'vencido' => 'text-danger-600 dark:text-danger-400',
                            'por_vencer' => 'text-warning-600 dark:text-warning-400',
                            default => '',
                        };

                        return new HtmlString('<span class="'.$clase.'">'.e(Formato::rel($prox)).'</span>');
                    })
                    ->placeholder('—'),
                TextColumn::make('total_pen')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Formato::pen($state))
                    ->description(fn (Contrato $r) => Formato::usd($r->total_usd))
                    ->sortable()
                    ->visible(fn () => auth()->user()->puedeVer('montos')),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Contrato $r) => $r->estadoVisible())
                    ->formatStateUsing(fn ($state) => Ui::estado($state)->getLabel())
                    ->color(fn ($state) => Ui::estado($state)->getColor())
                    ->icon(fn ($state) => Ui::estado($state)->getIcon()),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->hiddenLabel()
                    ->tooltip('Ver')
                    ->modalWidth(Width::SixExtraLarge)
                    ->modalHeading(fn (Contrato $r) => $r->numero)
                    ->modalDescription(fn (Contrato $r) => $r->cliente->razon_social)
                    ->modalIcon(Heroicon::OutlinedDocumentText)
                    ->extraModalFooterActions(fn (Contrato $r) => [
                        self::accionCancelar(),
                        self::accionEditarDesdeVer(),
                        self::accionRenovar(),
                        self::accionSubirEvidencia(),
                        self::accionActivar(),
                        AccionesDocumento::verPdf('verPdfDesdeVer')->visible(fn () => $r->estado !== 'borrador' && auth()->user()->puedeVer('montos')),
                    ]),
                ActionGroup::make([
                    self::accionEditar(),
                    self::accionRenovar(),
                    AccionesDocumento::verPdf(),
                    AccionesDocumento::enviarCorreo(),
                    self::accionDuplicar(),
                    self::accionCancelar(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('Aún no hay contratos')
            ->emptyStateDescription('Crea el primero o convierte una cotización aceptada.');
    }

    public static function accionEditar(): EditAction
    {
        $borrador = fn (Contrato $r) => $r->estado === 'borrador';

        return EditAction::make()
            ->visible(fn (Contrato $r) => $r->estado !== 'cancelado' && auth()->user()->puedeEditar('documentos'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalHeading(fn (Contrato $r) => "Editar {$r->numero}")
            ->modalDescription('Los cambios regeneran el PDF del contrato.')
            ->modalSubmitActionLabel(fn (Contrato $r) => $borrador($r) ? 'Guardar borrador' : 'Guardar cambios')
            ->extraModalFooterActions(fn (EditAction $action, Contrato $r) => $borrador($r) ? [
                $action->makeModalSubmitAction('activar', ['activar' => true])
                    ->label('Guardar y generar PDF')
                    ->icon(Heroicon::OutlinedDocumentCheck),
            ] : [])
            ->successNotification(null)
            ->after(fn (Contrato $record, array $arguments) => self::despuesDeGuardar($record, activar: $arguments['activar'] ?? false));
    }

    /** Abre el editor desde el modal de detalle. */
    public static function accionEditarDesdeVer(): Action
    {
        return Action::make('editarDesdeVer')
            ->label('Editar')
            ->color('gray')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Contrato $record) => $record->estado !== 'cancelado' && auth()->user()->puedeEditar('documentos'))
            ->action(fn (Contrato $record, $livewire) => $livewire->replaceMountedAction('edit', [], ['table' => true, 'recordKey' => $record->getKey()]));
    }

    /**
     * Después de crear o editar: totales, historial, activación, dominios y PDF.
     */
    public static function despuesDeGuardar(Contrato $contrato, bool $activar = false, bool $creado = false): void
    {
        $evidenciaNueva = $contrato->documento_evidencia_path && ($creado || $contrato->wasChanged('documento_evidencia_path'));
        $contrato->recalcularTotales();

        if ($creado) {
            $contrato->registrar('Contrato creado');
        }
        if ($evidenciaNueva) {
            $contrato->registrar("Documento firmado subido: {$contrato->documento_evidencia_nombre}");
        }

        if ($activar && $contrato->lineas()->doesntExist()) {
            Notification::make()->title('Agrega al menos un servicio')->body('Un contrato activo necesita servicios. Quedó como borrador.')->danger()->send();

            return;
        }

        if ($activar) {
            $contrato->update(['estado' => 'activo']);
            $contrato->registrar('Contrato activado y PDF generado');
        } elseif ($contrato->estado === 'activo' && ! $creado) {
            $contrato->registrar('Contrato editado, PDF regenerado');
        }

        if ($contrato->estado === 'activo') {
            $contrato->registrarDominios();
            app(DocumentoPdf::class)->guardar($contrato);
            Notification::make()->title('Contrato guardado')->body("{$contrato->numero} · PDF generado")->success()->send();
        } else {
            Notification::make()->title('Borrador guardado')->body($contrato->numero)->success()->send();
        }
    }

    public static function accionActivar(): Action
    {
        return Action::make('activar')
            ->label('Activar y generar PDF')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->visible(fn (Contrato $record) => $record->estado === 'borrador' && auth()->user()->puedeEditar('documentos'))
            ->requiresConfirmation()
            ->modalHeading(fn (Contrato $record) => "Activar {$record->numero}")
            ->modalDescription('El contrato pasa a activo, se registran sus dominios y se genera el PDF.')
            ->action(function (Contrato $record, Action $action) {
                if ($record->lineas()->doesntExist()) {
                    Notification::make()->title('El contrato no tiene servicios')->body('Edítalo y agrega al menos uno.')->danger()->send();
                    $action->halt();
                }
                $record->update(['estado' => 'activo']);
                $record->registrar('Contrato activado y PDF generado');
                $record->registrarDominios();
                $record->recalcularTotales();
                app(DocumentoPdf::class)->guardar($record);
                Notification::make()->title('Contrato activado')->body("{$record->numero} · PDF generado")->success()->send();
            });
    }

    /** Renueva las líneas elegidas un periodo desde su vencimiento actual (no desde hoy). */
    public static function accionRenovar(): Action
    {
        $renovables = fn (Contrato $r) => $r->lineas->filter(fn (ContratoLinea $l) => $l->renueva());

        return Action::make('renovar')
            ->label('Renovar servicios')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Contrato $record) => $record->estado === 'activo' && auth()->user()->puedeEditar('documentos'))
            ->modalHeading(fn (Contrato $record) => "Renovar servicios · {$record->numero}")
            ->modalDescription(fn (Contrato $record) => $record->cliente->razon_social)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Renovar seleccionados')
            ->modalHidden(fn (Contrato $record) => $renovables($record)->isEmpty())
            ->fillForm(fn (Contrato $record) => [
                'lineas' => $renovables($record)->filter(fn ($l) => $l->alerta() !== 'ok')->pluck('id')->all(),
                'tipo_cambio' => Empresa::tc(),
                'ajuste' => 0,
            ])
            ->schema(fn (Contrato $record) => [
                Text::make('Marca los servicios a renovar. Cada uno se extiende un periodo desde su vencimiento actual, no desde hoy.')->color('gray'),
                CheckboxList::make('lineas')
                    ->hiddenLabel()
                    ->required()
                    ->live()
                    ->options($renovables($record)->mapWithKeys(fn (ContratoLinea $l) => [$l->id => $l->etiqueta]))
                    ->descriptions($renovables($record)->mapWithKeys(fn (ContratoLinea $l) => [$l->id => $l->nombre.' · '.$l->periodo->getLabel()
                        .' · vence '.Formato::fecha($l->vence_el).' → '.Formato::fecha($l->vence_el->copy()->addMonthsNoOverflow($l->periodo->meses()))])),
                Grid::make(2)->schema([
                    TextInput::make('tipo_cambio')->label('Tipo de cambio para la renovación')->numeric()->step(0.001)->prefix('S/')
                        ->required()->gt(0)->live(onBlur: true)
                        ->helperText('Actual del contrato: '.Formato::num($record->tipo_cambio, 3))
                        ->validationMessages(['gt' => 'Debe ser mayor a 0.']),
                    TextInput::make('ajuste')->label('Ajuste de precio')->numeric()->step(0.01)->suffix('%')->default(0)->live(onBlur: true)
                        ->helperText('Positivo sube, negativo baja.'),
                ]),
                Text::make(function (Get $get) use ($record) {
                    $k = 1 + (float) $get('ajuste') / 100;
                    $tc = (float) $get('tipo_cambio') ?: 1;
                    $monto = $record->lineas->whereIn('id', $get('lineas') ?? [])
                        ->sum(fn ($l) => Montos::subtotal(['precio' => $l->precio * $k, 'moneda' => $l->moneda, 'cantidad' => $l->cantidad], $tc)['pen']);
                    $monto *= $record->aplica_igv ? 1 + Empresa::igv() : 1;

                    return 'Monto a facturar por la renovación: '.Formato::pen($monto).($record->aplica_igv ? ' (con IGV)' : '');
                })->weight('bold'),
            ])
            ->action(function (Contrato $record, array $data) use ($renovables) {
                if ($renovables($record)->isEmpty()) {
                    Notification::make()->title('Nada que renovar')->body('Este contrato solo tiene servicios de pago único.')->info()->send();

                    return;
                }
                $k = 1 + (float) $data['ajuste'] / 100;
                $renovadas = DB::transaction(function () use ($record, $data, $k) {
                    $renovadas = [];
                    foreach ($record->lineas->whereIn('id', $data['lineas']) as $l) {
                        $l->fecha_inicio = $l->vence_el;
                        if ($k != 1) {
                            $l->precio = round($l->precio * $k, 2);
                        }
                        $l->save();
                        $renovadas[] = $l->etiqueta;
                    }
                    $record->update(['tipo_cambio' => $data['tipo_cambio'], 'fecha_renovacion' => today()]);

                    return $renovadas;
                });
                $record->recalcularTotales();
                $record->registrar('Renovados: '.implode(', ', $renovadas).($k != 1 ? ' (ajuste '.$data['ajuste'].'%)' : ''));
                app(DocumentoPdf::class)->guardar($record);
                Notification::make()->title('Servicios renovados')->body(count($renovadas).' servicio(s) extendidos. PDF actualizado.')->success()->send();
            });
    }

    public static function accionCancelar(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar contrato')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Contrato $record) => $record->estado !== 'cancelado' && auth()->user()->puedeEditar('documentos'))
            ->requiresConfirmation()
            ->modalHeading(fn (Contrato $record) => "Cancelar {$record->numero}")
            ->modalDescription('Los servicios dejarán de renovarse y el contrato quedará como cancelado. Esta acción queda en el historial.')
            ->modalSubmitActionLabel('Cancelar contrato')
            ->modalCancelActionLabel('Volver')
            ->action(function (Contrato $record) {
                $record->update(['estado' => 'cancelado']);
                $record->registrar('Contrato cancelado');
                Notification::make()->title('Contrato cancelado')->body($record->numero)->info()->send();
            });
    }

    public static function accionDuplicar(): Action
    {
        return Action::make('duplicar')
            ->label('Duplicar')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->visible(fn () => auth()->user()->puedeEditar('documentos'))
            ->action(function (Contrato $record, $livewire) {
                $copia = DB::transaction(function () use ($record) {
                    $copia = $record->replicate(['uuid', 'numero', 'pdf_path', 'documento_evidencia_path', 'documento_evidencia_nombre', 'cotizacion_id', 'fecha_renovacion']);
                    $copia->fill(['estado' => 'borrador', 'fecha_inicio' => today()])->save();
                    foreach ($record->lineas as $l) {
                        $copia->lineas()->create($l->replicate(['contrato_id', 'vence_el'])->fill(['fecha_inicio' => today()])->toArray());
                    }
                    $copia->recalcularTotales();
                    $copia->registrar("Duplicado de {$record->numero}");

                    return $copia;
                });
                Notification::make()->title('Contrato duplicado')->body("{$copia->numero} quedó como borrador.")->success()->send();
                $livewire->replaceMountedAction('edit', [], ['table' => true, 'recordKey' => $copia->getKey()]);
            });
    }

    public static function accionSubirEvidencia(): Action
    {
        return Action::make('subirEvidencia')
            ->label('Subir documento firmado')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (Contrato $record) => ! $record->documento_evidencia_path && auth()->user()->puedeEditar('documentos'))
            ->modalWidth(Width::Large)
            ->schema([
                FileUpload::make('archivo')
                    ->label('Contrato firmado (PDF, máx. 5 MB)')
                    ->disk('local')
                    ->directory('evidencias')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(5120)
                    ->storeFileNamesIn('nombre')
                    ->required()
                    ->validationMessages(['mimetypes' => 'Debe ser PDF.', 'max' => 'Supera 5 MB.']),
            ])
            ->action(function (Contrato $record, array $data) {
                $record->update(['documento_evidencia_path' => $data['archivo'], 'documento_evidencia_nombre' => $data['nombre']]);
                $record->registrar("Documento firmado subido: {$data['nombre']}");
                Notification::make()->title('Documento firmado guardado')->body($data['nombre'])->success()->send();
            });
    }

    public static function urlVer(int $id): string
    {
        return static::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $id]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContratos::route('/'),
        ];
    }
}
