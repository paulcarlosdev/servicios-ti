<?php

namespace App\Filament\Resources\Servicios;

use App\Enums\TipoLinea;
use App\Filament\Resources\Servicios\Pages\ManageServicios;
use App\Filament\Support\Ui;
use App\Models\Servicio;
use App\Support\Formato;
use App\Support\Montos;
use App\Enums\Periodo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

/** VPS y Hosting (HU-004): planes con recursos y precios por periodo, mostrados en listado (docs/prototype/servicios.html). */
class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'VPS y Hosting';

    protected static ?string $navigationLabel = 'VPS y Hosting';

    protected static ?string $slug = 'vps-hosting';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    public static function form(Schema $schema): Schema
    {
        $esVps = fn (Get $get) => ($get('tipo') instanceof TipoLinea ? $get('tipo')->value : $get('tipo')) === 'vps';

        return $schema->columns(1)->components([
            ToggleButtons::make('tipo')
                ->label('Tipo de servicio')
                ->options(['vps' => 'VPS', 'hosting' => 'Hosting'])
                ->icons(['vps' => Heroicon::OutlinedServer, 'hosting' => Heroicon::OutlinedCloud])
                ->inline()
                ->required()
                ->live(),
            Grid::make(6)->schema([
                TextInput::make('nombre')->label('Nombre del plan')->required()->maxLength(255)->placeholder('VPS Pro')->columnSpan(6)
                    ->validationMessages(['required' => 'El nombre es obligatorio.']),
                TextInput::make('cpu')->label('vCPU')->integer()->minValue(1)->required()->columnSpan(2)->visible($esVps),
                TextInput::make('ram')->label('RAM')->integer()->minValue(1)->suffix('GB')->required()->columnSpan(2)->visible($esVps),
                TextInput::make('disco')->label(fn (Get $get) => $esVps($get) ? 'Disco SSD' : 'Almacenamiento')
                    ->integer()->minValue(1)->suffix('GB')->required()->columnSpan(fn (Get $get) => $esVps($get) ? 2 : 3),
                TextInput::make('sitios')->label('Sitios web')->integer()->minValue(1)->required()->columnSpan(3)
                    ->visible(fn (Get $get) => ! $esVps($get)),
                TextInput::make('extra')->label('Características incluidas')->columnSpan(6)
                    ->placeholder('IP dedicada, Backup diario')
                    ->helperText('Separadas por comas. Se muestran en el listado.')
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace(' · ', ', ', $state) : null)
                    ->dehydrateStateUsing(fn (?string $state) => collect(explode(',', (string) $state))->map('trim')->filter()->join(' · ') ?: null),
            ]),
            Section::make('Precios por periodo')
                ->compact()
                ->headerActions([
                    Action::make('sugerir')
                        ->label('Sugerir con descuento')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->link()
                        ->action(function (Get $get, Set $set) {
                            $mensual = (float) $get('precio_mensual');
                            if ($mensual <= 0) {
                                Notification::make()->title('Ingresa primero el precio mensual.')->warning()->send();

                                return;
                            }
                            $set('precio_semestral', round($mensual * 6 * 0.9));
                            $set('precio_anual', round($mensual * 12 * 0.8));
                        }),
                ])
                ->columns(4)
                ->schema([
                    Select::make('moneda')->label('Moneda')->options(['USD' => 'USD $', 'PEN' => 'PEN S/'])->default('USD')->required()->selectablePlaceholder(false)->live(),
                    TextInput::make('precio_mensual')->label('Mensual')->numeric()->step(0.01)->required()->gt(0)->live(onBlur: true)
                        ->validationMessages(['gt' => 'Precio mensual mayor a 0.']),
                    TextInput::make('precio_semestral')->label('Semestral')->numeric()->step(0.01)->minValue(0)->live(onBlur: true)
                        ->validationMessages(['min' => 'No negativo.']),
                    TextInput::make('precio_anual')->label('Anual')->numeric()->step(0.01)->minValue(0)->live(onBlur: true)
                        ->validationMessages(['min' => 'No negativo.']),
                    Text::make(function (Get $get) {
                        $m = (float) $get('precio_mensual');
                        $s = Montos::ahorro($m, (float) $get('precio_semestral'), Periodo::Semestral);
                        $a = Montos::ahorro($m, (float) $get('precio_anual'), Periodo::Anual);

                        return $s || $a
                            ? 'Semestral ahorra '.($s ?? 0).'% · anual ahorra '.($a ?? 0).'% frente al pago mensual.'
                            : 'Deja vacío un periodo si no se ofrece.';
                    })->color('gray')->columnSpanFull(),
                ]),
            Grid::make(2)->schema([
                Toggle::make('destacado')->label('Marcar como popular')->helperText('Se resalta en el catálogo y al cotizar.'),
                Ui::estadoToggle('Disponible', 'Los planes inactivos no se ofrecen en contratos nuevos.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        // Una columna por periodo, con el % de ahorro frente a pagar mensual
        $precio = fn (Periodo $p) => TextColumn::make($p->columnaPrecio())
            ->label($p->getLabel())
            ->alignEnd()
            ->visibleFrom('md')
            ->formatStateUsing(fn ($state, Servicio $record) => Formato::money($state, $record->moneda))
            ->description(fn (Servicio $record) => ($a = Montos::ahorro($record->precio_mensual, $record->{$p->columnaPrecio()}, $p)) ? "ahorra {$a}%" : null)
            ->placeholder('—');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum(
                ['lineas as uso' => fn ($lineas) => $lineas->whereHas('contrato', fn ($c) => $c->where('estado', '!=', 'cancelado'))],
                'cantidad',
            ))
            ->defaultSort('id')
            ->paginated(false)
            ->searchPlaceholder('Buscar plan')
            ->columns([
                // Chip del tipo + nombre y detalle, con el mismo diseño que la columna Cliente
                ViewColumn::make('nombre')
                    ->label('Plan')
                    ->view('filament.servicios.columna-plan')
                    ->searchable(['nombre', 'extra']),
                $precio(Periodo::Mensual),
                $precio(Periodo::Semestral),
                $precio(Periodo::Anual),
                Ui::estadoColumna(),
            ])
            ->recordAction('edit')
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip(fn (Servicio $record) => "Editar {$record->nombre}")
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (Servicio $record) => "Editar {$record->nombre}")
                    ->successNotificationTitle('Plan actualizado'),
                Action::make('eliminar')
                    ->hiddenLabel()
                    ->tooltip(fn (Servicio $record) => "Eliminar {$record->nombre}")
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->visible(fn () => auth()->user()->puedeEditar('catalogo'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Servicio $record) => "Eliminar {$record->nombre}")
                    ->modalDescription('El plan se quitará del catálogo.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->action(function (Servicio $record, Action $action) {
                        if ($record->lineas()->exists()) {
                            Notification::make()->title('No se puede eliminar')->body('El plan está en contratos. Desactívalo en su lugar.')->warning()->send();
                            $action->halt();
                        }
                        $record->delete();
                        Notification::make()->title('Plan eliminado')->info()->send();
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedServer)
            ->emptyStateHeading('Sin planes')
            ->emptyStateDescription('Registra un plan para ofrecerlo en cotizaciones y contratos.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServicios::route('/'),
        ];
    }
}
