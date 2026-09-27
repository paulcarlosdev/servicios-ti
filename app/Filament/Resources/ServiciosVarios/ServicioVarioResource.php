<?php

namespace App\Filament\Resources\ServiciosVarios;

use App\Enums\Periodo;
use App\Filament\Resources\ServiciosVarios\Pages\ManageServiciosVarios;
use App\Filament\Support\Ui;
use App\Models\ServicioVario;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

/** Servicios varios (HU-011): soporte, correo, certificados… con los periodos que apliquen. */
class ServicioVarioResource extends Resource
{
    protected static ?string $model = ServicioVario::class;

    protected static ?string $modelLabel = 'servicio vario';

    protected static ?string $pluralModelLabel = 'servicios varios';

    protected static ?string $slug = 'servicios-varios';

    protected static ?string $navigationLabel = 'Servicios varios';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    public static function form(Schema $schema): Schema
    {
        $precio = fn (Periodo $p) => TextInput::make($p->columnaPrecio())
            ->label($p->getLabel())
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->placeholder('—')
            ->validationMessages(['min' => 'No negativo.'])
            // ≤ 0 o vacío = el periodo no se ofrece
            ->dehydrateStateUsing(fn ($state) => (float) $state > 0 ? $state : null);

        return $schema->columns(4)->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->columnSpan(3)
                ->placeholder('Soporte técnico prioritario')
                ->validationMessages(['required' => 'El nombre es obligatorio.']),
            Select::make('moneda')->label('Moneda')->options(['PEN' => 'PEN S/', 'USD' => 'USD $'])->default('PEN')->required()->selectablePlaceholder(false),
            Textarea::make('descripcion')->label('Descripción')->rows(2)->columnSpanFull()
                ->placeholder('Qué incluye, alcance y condiciones'),
            Fieldset::make('Precios')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    $precio(Periodo::Mensual)
                        // Al menos un periodo con precio
                        ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $alguno = collect(Periodo::cases())->contains(fn ($p) => (float) $get($p->columnaPrecio()) > 0);
                            if (! $alguno) {
                                $fail('Indica el precio de al menos un periodo.');
                            }
                        }),
                    $precio(Periodo::Semestral),
                    $precio(Periodo::Anual),
                    $precio(Periodo::Unico),
                ]),
            Text::make('Completa solo los periodos que ofreces. Al menos uno es obligatorio.')->color('gray')->columnSpanFull(),
            Ui::estadoToggle('Disponible para cotizar y contratar')->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.servicios-varios.detalle')->viewData(fn (ServicioVario $record) => ['servicio' => $record]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $precio = fn (Periodo $p) => TextColumn::make($p->columnaPrecio())
            ->label($p->getLabel())
            ->alignEnd()
            ->visibleFrom('md')
            ->formatStateUsing(fn ($state, ServicioVario $r) => Formato::money($state, $r->moneda))
            ->placeholder('—');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('lineasEnUso'))
            ->defaultSort('id') // orden del catálogo, igual que VPS y Hosting
            ->searchPlaceholder('Buscar servicio')
            ->columns([
                // Chip ámbar + nombre, descripción y moneda (docs/prototype/servicios-varios.html)
                ViewColumn::make('nombre')
                    ->label('Servicio')
                    ->view('filament.servicios-varios.columna-servicio')
                    ->searchable(['nombre', 'descripcion']),
                $precio(Periodo::Mensual),
                $precio(Periodo::Semestral),
                $precio(Periodo::Anual),
                $precio(Periodo::Unico),
                Ui::estadoColumna(),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()->hiddenLabel()->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (ServicioVario $r) => $r->nombre)
                    ->modalDescription(fn (ServicioVario $r) => $r->descripcion),
                EditAction::make()->hiddenLabel()->tooltip('Editar')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (ServicioVario $r) => "Editar {$r->nombre}")
                    ->successNotificationTitle('Servicio actualizado'),
                Action::make('eliminar')
                    ->hiddenLabel()
                    ->tooltip('Eliminar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn () => auth()->user()->puedeEditar('catalogo'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (ServicioVario $r) => "Eliminar {$r->nombre}")
                    ->modalDescription('Se quitará del catálogo.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->action(function (ServicioVario $r, Action $action) {
                        if ($r->lineas()->exists()) {
                            Notification::make()->title('No se puede eliminar')->body('Está en contratos. Desactívalo para que no se ofrezca.')->warning()->send();
                            $action->halt();
                        }
                        $r->delete();
                        Notification::make()->title('Servicio eliminado')->info()->send();
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPuzzlePiece)
            ->emptyStateHeading('Sin servicios')
            ->emptyStateDescription('Registra un servicio adicional para ofrecerlo en cotizaciones y contratos.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServiciosVarios::route('/'),
        ];
    }
}
