<?php

namespace App\Filament\Resources\Clientes;

use App\Filament\Resources\Clientes\Pages\ManageClientes;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Filament\Support\Ui;
use App\Models\Cliente;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Clientes (HU-003). Correo, teléfono y dirección se muestran solo en la ficha. */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static string|\UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'razon_social';

    public static function getGloballySearchableAttributes(): array
    {
        return ['razon_social', 'documento', 'contacto'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [$record->documento_completo];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(6)->components(self::campos());
    }

    /** Campos del cliente; también se usan en "Nuevo" dentro de contratos y cotizaciones. */
    public static function campos(): array
    {
        $esEdicion = fn (string $operation) => $operation === 'edit';

        return [
            TextInput::make('razon_social')
                ->label('Nombre o razón social')
                ->required()
                ->maxLength(255)
                ->columnSpan(6)
                ->validationMessages(['required' => 'El nombre es obligatorio.']),
            Select::make('tipo_doc')
                ->label('Documento')
                ->options(['RUC' => 'RUC', 'DNI' => 'DNI', 'CE' => 'Carné de extranjería'])
                ->default('RUC')
                ->required()
                ->selectablePlaceholder(false)
                ->live()
                ->disabled($esEdicion)
                ->columnSpan(2),
            TextInput::make('documento')
                ->label('Número')
                ->required()
                ->maxLength(12)
                ->inputMode('numeric')
                ->disabled($esEdicion)
                ->unique(ignoreRecord: true)
                ->regex(fn (Get $get) => match ($get('tipo_doc')) {
                    'DNI' => '/^\d{8}$/',
                    'CE' => '/^\w{8,12}$/',
                    default => '/^(10|15|17|20)\d{9}$/',
                })
                ->validationMessages([
                    'unique' => 'Ya existe un cliente con este documento.',
                    'regex' => fn (Get $get) => match ($get('tipo_doc')) {
                        'DNI' => 'El DNI tiene 8 dígitos.',
                        'CE' => 'Ingresa un carné válido.',
                        default => 'El RUC tiene 11 dígitos y empieza con 10, 15, 17 o 20.',
                    },
                ])
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'El documento no se puede modificar.' : 'RUC de 11 dígitos o DNI de 8.')
                ->columnSpan(4),
            TextInput::make('contacto')->label('Persona de contacto')->maxLength(255)->columnSpan(3),
            TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30)->placeholder('+51 9XX XXX XXX')->columnSpan(3),
            TextInput::make('email')
                ->label('Correo principal')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'Este correo ya pertenece a otro cliente.', 'email' => 'Ingresa un correo válido.'])
                ->columnSpan(3),
            TextInput::make('email_secundario')
                ->label('Correo de facturación')
                ->email()
                ->placeholder('Opcional')
                ->columnSpan(3),
            TextInput::make('direccion')->label('Dirección')->maxLength(255)->columnSpan(6),
            Ui::estadoToggle('Cliente activo', 'Los clientes inactivos no aparecen al crear contratos.')
                ->visible($esEdicion)
                ->columnSpan(6),
        ];
    }

    /** El contacto vacío se guarda como la razón social. */
    public static function normalizar(array $data): array
    {
        $data['contacto'] = filled($data['contacto'] ?? null) ? $data['contacto'] : ($data['razon_social'] ?? null);
        $data['email'] = mb_strtolower($data['email'] ?? '');

        return $data;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.clientes.ficha')->viewData(fn (Cliente $record) => ['cliente' => $record]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['lineasVigentes']))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Nombre, RUC/DNI, correo, teléfono o contacto')
            ->columns([
                // Avatar con iniciales + razón social + documento (docs/prototype/clientes.html)
                ViewColumn::make('razon_social')
                    ->label('Cliente')
                    ->view('filament.clientes.columna-cliente')
                    ->searchable(['razon_social', 'documento', 'contacto', 'email', 'email_secundario', 'telefono'])
                    ->sortable(),
                TextColumn::make('contacto')->label('Contacto')->description('Contacto')->visibleFrom('md'),
                ViewColumn::make('servicios')->label('Servicios')->view('filament.clientes.columna-servicios'),
                TextColumn::make('proximo_vencimiento')
                    ->label('Próximo vencimiento')
                    ->state(fn (Cliente $r) => $r->lineasVigentes->pluck('vence_el')->filter()->sort()->first())
                    ->formatStateUsing(fn ($state) => Formato::fecha($state))
                    // La fecha en color normal; el "en N días / hace N días" según la alerta de la línea
                    ->description(function (Cliente $r) {
                        $linea = $r->lineasVigentes->filter(fn ($l) => $l->vence_el)->sortBy('vence_el')->first();
                        if (! $linea) {
                            return null;
                        }
                        $clase = match ($linea->alerta()) {
                            'vencido' => 'text-danger-600 dark:text-danger-400',
                            'por_vencer' => 'text-warning-600 dark:text-warning-400',
                            default => '',
                        };

                        return new HtmlString('<span class="'.$clase.'">'.e(Formato::rel($linea->vence_el)).'</span>');
                    })
                    ->placeholder('—'),
                Ui::estadoColumna(),
            ])            ->recordAction('view')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver ficha')->modalWidth(Width::FourExtraLarge)
                        ->modalHeading(fn (Cliente $r) => $r->razon_social)
                        ->modalDescription(fn (Cliente $r) => $r->documento_completo.' · cliente desde '.Formato::fecha($r->created_at))
                        ->extraModalFooterActions(fn (Cliente $r) => [
                            Action::make('cotizarFicha')->label('Cotizar')->color('gray')
                                ->url(CotizacionResource::getUrl('index', ['action' => 'create', 'actionArguments' => ['cliente' => $r->id]]))
                                ->visible(fn () => auth()->user()->puedeEditar('documentos')),
                            Action::make('contratoFicha')->label('Nuevo contrato')->icon(Heroicon::OutlinedDocumentPlus)
                                ->url(ContratoResource::getUrl('index', ['action' => 'create', 'actionArguments' => ['cliente' => $r->id]]))
                                ->visible(fn () => auth()->user()->puedeEditar('documentos')),
                        ]),
                    EditAction::make()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Editar cliente')
                        ->modalDescription(fn (Cliente $r) => $r->razon_social)
                        ->mutateDataUsing(fn (array $data) => self::normalizar($data))
                        ->successNotificationTitle('Cliente actualizado'),
                    Action::make('nuevaCotizacion')
                        ->label('Nueva cotización')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->url(fn (Cliente $r) => CotizacionResource::getUrl('index', ['action' => 'create', 'actionArguments' => ['cliente' => $r->id]]))
                        ->visible(fn () => auth()->user()->puedeEditar('documentos')),
                    Action::make('nuevoContrato')
                        ->label('Nuevo contrato')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (Cliente $r) => ContratoResource::getUrl('index', ['action' => 'create', 'actionArguments' => ['cliente' => $r->id]]))
                        ->visible(fn () => auth()->user()->puedeEditar('documentos')),
                    self::accionEliminar(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('No se encontraron clientes')
            ->emptyStateDescription('Prueba con otro dato o registra un cliente nuevo.');
    }

    /** Con contratos no se elimina: se ofrece marcarlo inactivo para conservar el historial. */
    public static function accionEliminar(): Action
    {
        $tieneContratos = fn (Cliente $r) => $r->contratos()->exists();

        return Action::make('eliminar')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn () => auth()->user()->puedeEditar('clientes'))
            ->requiresConfirmation()
            ->modalIcon(fn (Cliente $r) => $tieneContratos($r) ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedTrash)
            ->modalHeading(fn (Cliente $r) => $tieneContratos($r) ? 'No se puede eliminar' : 'Eliminar cliente')
            ->modalDescription(fn (Cliente $r) => $tieneContratos($r)
                ? "{$r->razon_social} tiene {$r->contratos()->count()} contrato(s) asociados. Para conservar el historial, márcalo como inactivo: no aparecerá al crear contratos nuevos."
                : "¿Eliminar a {$r->razon_social}? No tiene contratos asociados.")
            ->modalSubmitActionLabel(fn (Cliente $r) => $tieneContratos($r) ? 'Marcar inactivo' : 'Eliminar')
            ->modalSubmitAction(fn (Action $action, Cliente $r) => $tieneContratos($r) && $r->estado === 'inactivo' ? false : $action)
            ->modalCancelActionLabel(fn (Cliente $r) => $tieneContratos($r) ? 'Cerrar' : 'Cancelar')
            ->action(function (Cliente $r) use ($tieneContratos) {
                if ($tieneContratos($r)) {
                    $r->update(['estado' => 'inactivo']);
                    Notification::make()->title('Cliente marcado como inactivo')->body($r->razon_social)->info()->send();

                    return;
                }
                $r->delete();
                Notification::make()->title('Cliente eliminado')->body($r->razon_social)->info()->send();
            });
    }

    public static function urlVer(int $id): string
    {
        return static::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $id]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClientes::route('/'),
        ];
    }
}
