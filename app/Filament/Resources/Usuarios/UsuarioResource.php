<?php

namespace App\Filament\Resources\Usuarios;

use App\Enums\Rol;
use App\Filament\Resources\Usuarios\Pages\ManageUsuarios;
use App\Filament\Support\Contrasena;
use App\Filament\Support\Ui;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Usuarios y roles (HU-014). Solo Admin gestiona usuarios. */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $slug = 'usuarios';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        $esYo = fn (?User $record) => $record?->is(auth()->user()) ?? false;

        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre completo')->required()->maxLength(255)
                ->validationMessages(['required' => 'El nombre es obligatorio.']),
            TextInput::make('email')->label('Correo')->email()->required()->maxLength(255)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim($state)))
                ->validationMessages(['unique' => 'Ya existe un usuario con este correo.', 'email' => 'Ingresa un correo válido.']),
            TextInput::make('password')
                ->label('Contraseña temporal')
                ->required()
                ->minLength(8)
                ->helperText('Mínimo 8 caracteres.')
                ->validationMessages(['min' => 'Mínimo 8 caracteres.'])
                ->visibleOn('create')
                ->columnSpanFull()
                ->hintAction(Action::make('generar')
                    ->label('Generar una segura')
                    ->icon(Heroicon::OutlinedKey)
                    ->action(fn (Set $set) => $set('password', self::contrasenaSegura()))),
            Radio::make('rol')
                ->label('Rol')
                ->options(Rol::class)
                ->descriptions(collect(Rol::cases())->mapWithKeys(fn (Rol $r) => [$r->value => $r->getDescription()])->all())
                ->default(Rol::Gerente->value)
                ->required()
                ->columns(2)
                ->disableOptionWhen(fn (string $value, ?User $record) => $esYo($record) && $value !== Rol::Admin->value)
                ->helperText(fn (?User $record) => $esYo($record) ? 'No puedes quitarte el rol de Admin a ti mismo.' : null)
                ->columnSpanFull(),
        ]);
    }

    /** 14 caracteres sin ambiguos (0/O, 1/l/I). */
    public static function contrasenaSegura(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!#$%';

        return collect(range(1, 14))->map(fn () => $alfabeto[random_int(0, strlen($alfabeto) - 1)])->join('');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('medium')
                    ->formatStateUsing(fn (User $r) => $r->name.($r->is(auth()->user()) ? ' (tú)' : ''))
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->description(fn (User $r) => $r->ultimo_acceso_at
                        ? 'Último acceso '.\App\Support\Formato::rel($r->ultimo_acceso_at)
                        : 'Aún no ingresa'),
                Ui::estadoColumna(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Editar usuario')
                        ->modalDescription(fn (User $r) => $r->email)
                        ->mutateDataUsing(fn (array $data, User $record) => $record->is(auth()->user()) ? ['rol' => Rol::Admin->value] + $data : $data)
                        ->successNotificationTitle('Usuario actualizado')
                        ->successNotification(fn ($notification, User $record) => $notification->body("{$record->name} · {$record->rol->getLabel()}")),
                    Contrasena::accionAdmin(),
                    Action::make('enviarEnlace')
                        ->label('Enviar enlace para nueva contraseña')
                        ->icon(Heroicon::OutlinedKey)
                        ->action(function (User $r) {
                            Password::broker()->sendResetLink(['email' => $r->email]);
                            Notification::make()->title('Enlace enviado')->body("Se envió a {$r->email}.")->info()->send();
                        }),
                    Action::make('alternarEstado')
                        ->label(fn (User $r) => $r->estado === 'activo' ? 'Desactivar' : 'Activar')
                        ->icon(fn (User $r) => $r->estado === 'activo' ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                        ->color(fn (User $r) => $r->estado === 'activo' ? 'danger' : null)
                        ->disabled(fn (User $r) => $r->is(auth()->user()))
                        ->tooltip(fn (User $r) => $r->is(auth()->user()) ? 'Tú' : null)
                        ->requiresConfirmation(fn (User $r) => $r->estado === 'activo')
                        ->modalHeading(fn (User $r) => "Desactivar a {$r->name}")
                        ->modalDescription('No podrá ingresar al panel y se cerrarán sus sesiones abiertas.')
                        ->modalSubmitActionLabel('Desactivar')
                        ->action(function (User $r) {
                            $activo = $r->estado !== 'activo';
                            $r->forceFill(['estado' => $activo ? 'activo' : 'inactivo', 'remember_token' => Str::random(60)])->save();
                            if (! $activo) {
                                DB::table('sessions')->where('user_id', $r->id)->delete(); // revoca sus sesiones
                            }
                            Notification::make()->title($activo ? 'Usuario activado' : 'Usuario desactivado')->body($r->name)->info()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsuarios::route('/'),
        ];
    }
}
