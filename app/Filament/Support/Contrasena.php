<?php

namespace App\Filament\Support;

use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Cambio de contraseña: la propia (menú de usuario) y la de otros usuarios (solo Admin, en /admin/usuarios).
 * Al cambiarla se cierran las demás sesiones del usuario y se invalida su "Recordarme".
 */
class Contrasena
{
    /** Acción del menú de usuario (arriba a la derecha): exige la contraseña actual. */
    public static function accionPropia(): Action
    {
        return Action::make('cambiarContrasena')
            ->label('Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->modalHeading('Cambiar mi contraseña')
            ->modalDescription('Se cerrarán tus sesiones abiertas en otros dispositivos.')
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Cambiar contraseña')
            ->schema([
                TextInput::make('actual')
                    ->label('Contraseña actual')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('current-password')
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->validationMessages([
                        'required' => 'Ingresa tu contraseña actual.',
                        'current_password' => 'La contraseña actual no es correcta.',
                    ])
                    ->dehydrated(false),
                self::campoNueva(),
                self::campoConfirmacion(),
            ])
            ->action(function (array $data): void {
                self::aplicar(auth()->user(), $data['password'], propia: true);

                Notification::make()->title('Contraseña actualizada')
                    ->body('Tus otras sesiones se cerraron.')->success()->send();
            });
    }

    /** Acción de fila en Usuarios: el Admin fija una nueva contraseña a otro usuario. */
    public static function accionAdmin(): Action
    {
        return Action::make('cambiarContrasenaUsuario')
            ->label('Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (User $record): bool => auth()->user()?->esAdmin() && ! $record->is(auth()->user()))
            ->authorize(fn (): bool => auth()->user()?->esAdmin() ?? false)
            ->modalHeading(fn (User $record): string => "Cambiar contraseña de {$record->name}")
            ->modalDescription(fn (User $record): string => "{$record->email} · Se cerrarán todas sus sesiones abiertas.")
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Cambiar contraseña')
            ->schema([
                self::campoNueva(conGenerador: true)->label('Nueva contraseña'),
                self::campoConfirmacion(),
            ])
            ->action(function (array $data, User $record): void {
                self::aplicar($record, $data['password'], propia: false);

                Notification::make()->title('Contraseña actualizada')
                    ->body("Comunícale la nueva contraseña a {$record->name} por un medio seguro.")->success()->send();
            });
    }

    public static function campoNueva(bool $conGenerador = false): TextInput
    {
        $campo = TextInput::make('password')
            ->label('Nueva contraseña')
            ->password()
            ->revealable()
            ->required()
            ->autocomplete('new-password')
            ->minLength(8)
            ->same('passwordConfirmation')
            ->helperText('Mínimo 8 caracteres.')
            ->validationMessages([
                'required' => 'Ingresa la nueva contraseña.',
                'min' => 'Mínimo 8 caracteres.',
                'same' => 'Las contraseñas no coinciden.',
            ]);

        if ($conGenerador) {
            $campo->hintAction(Action::make('generar')
                ->label('Generar una segura')
                ->icon(Heroicon::OutlinedSparkles)
                ->action(function (Set $set): void {
                    $clave = UsuarioResource::contrasenaSegura();
                    $set('password', $clave);
                    $set('passwordConfirmation', $clave);
                }));
        }

        return $campo;
    }

    public static function campoConfirmacion(): TextInput
    {
        return TextInput::make('passwordConfirmation')
            ->label('Repite la nueva contraseña')
            ->password()
            ->revealable()
            ->required()
            ->autocomplete('new-password')
            ->validationMessages(['required' => 'Repite la nueva contraseña.'])
            ->dehydrated(false);
    }

    /** Guarda la contraseña (el cast "hashed" del modelo la encripta) y cierra las demás sesiones. */
    public static function aplicar(User $user, #[SensitiveParameter] string $nueva, bool $propia): void
    {
        $user->forceFill(['password' => $nueva, 'remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($propia, fn ($q) => $q->where('id', '!=', session()->getId()))
                ->delete();
        }

        if ($propia && request()->hasSession()) {
            // Mantiene la sesión actual: AuthenticateSession compara este hash en cada petición.
            request()->session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
        }
    }
}
