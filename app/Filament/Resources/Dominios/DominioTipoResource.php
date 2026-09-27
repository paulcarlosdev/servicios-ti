<?php

namespace App\Filament\Resources\Dominios;

use App\Filament\Resources\Dominios\Pages\ManageDominios;
use App\Filament\Support\Ui;
use App\Models\DominioTipo;
use App\Support\Empresa;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

/**
 * Dominios (HU-015): administrador de precios de las extensiones (docs/prototype/dominios.html).
 * El nombre de dominio de cada cliente se escribe en la línea de la cotización o contrato.
 */
class DominioTipoResource extends Resource
{
    protected static ?string $model = DominioTipo::class;

    protected static ?string $modelLabel = 'extensión';

    protected static ?string $pluralModelLabel = 'dominios';

    protected static ?string $navigationLabel = 'Dominios';

    protected static ?string $slug = 'dominios';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function form(Schema $schema): Schema
    {
        $tc = Empresa::tc();

        return $schema->columns(2)->components([
            TextInput::make('extension')
                ->label('Extensión')
                ->required()
                ->placeholder('.com.pe')
                ->helperText('Empieza con punto, p. ej. .pe o .com.pe')
                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim((string) $state)))
                ->regex('/^(\.[a-zA-Z]{2,})+$/')
                ->unique(ignoreRecord: true)
                ->validationMessages([
                    'regex' => 'Formato inválido: debe empezar con punto (p. ej. .pe).',
                    'unique' => 'Esta extensión ya existe.',
                ])
                ->columnSpanFull(),
            TextInput::make('precio_usd')->label('Precio anual USD')->numeric()->step(0.01)->minValue(0)->prefix('$')->required()
                ->validationMessages(['min' => 'Precio no negativo.']),
            TextInput::make('precio_pen')->label('Precio anual PEN')->numeric()->step(0.01)->minValue(0)->prefix('S/')->required()
                ->validationMessages(['min' => 'Precio no negativo.'])
                ->hintAction(Action::make('calcular')
                    ->label('Calcular con TC '.Formato::num($tc, 3))
                    ->action(fn (Get $get, Set $set) => $set('precio_pen', ceil((float) $get('precio_usd') * $tc)))),
            Ui::estadoToggle('Disponible para cotizar y contratar')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tc = Empresa::tc();

        return $table
            // Líneas de dominio en contratos no cancelados que usan la extensión
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'lineas as uso' => fn ($lineas) => $lineas->whereHas('contrato', fn ($c) => $c->where('estado', '!=', 'cancelado')),
            ]))
            ->defaultSort('id')
            ->paginated(false)
            ->searchPlaceholder('Buscar extensión')
            ->columns([
                ViewColumn::make('extension')
                    ->label('Extensión')
                    ->view('filament.dominios.columna-extension')
                    ->searchable(),
                TextColumn::make('precio_usd')
                    ->label('Precio anual USD')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Formato::usd($state)),
                TextColumn::make('precio_pen')
                    ->label('Precio anual PEN')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Formato::pen($state))
                    ->description(function (DominioTipo $record) use ($tc) {
                        $ref = $record->precio_usd * $tc;
                        $dif = $record->precio_pen - $ref;

                        return 'al TC hoy '.Formato::pen($ref).(abs($dif) >= 1 ? ' ('.($dif > 0 ? '+' : '−').Formato::pen(abs($dif)).')' : '');
                    }),
                Ui::estadoColumna(),
            ])
            ->recordAction('edit')
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->tooltip(fn (DominioTipo $record) => "Editar {$record->extension}")
                    ->modalWidth(Width::Large)
                    ->modalHeading(fn (DominioTipo $record) => "Editar extensión {$record->extension}")
                    ->successNotificationTitle('Extensión actualizada'),
                Action::make('eliminar')
                    ->hiddenLabel()
                    ->tooltip(fn (DominioTipo $record) => "Eliminar {$record->extension}")
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->visible(fn () => auth()->user()->puedeEditar('catalogo'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (DominioTipo $record) => "Eliminar {$record->extension}")
                    ->modalDescription('La extensión dejará de estar disponible.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->action(function (DominioTipo $record, Action $action) {
                        if ($record->lineas()->exists() || $record->registros()->exists()) {
                            Notification::make()->title("No se puede eliminar {$record->extension}")
                                ->body('Está en contratos. Desactívala para que no se ofrezca.')->warning()->send();
                            $action->halt();
                        }
                        $record->delete();
                        Notification::make()->title('Extensión eliminada')->info()->send();
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedGlobeAlt)
            ->emptyStateHeading('Sin extensiones')
            ->emptyStateDescription('Agrega una extensión con su precio para ofrecer dominios en cotizaciones y contratos.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDominios::route('/'),
        ];
    }
}
