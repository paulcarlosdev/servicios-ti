<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "Último acceso" de la pantalla Usuarios
        Event::listen(Login::class, fn (Login $event) => $event->user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly());

        // El enlace de "nueva contraseña" apunta al restablecimiento del panel Filament
        ResetPassword::createUrlUsing(fn ($user, string $token) => Filament::getPanel('admin')->getResetPasswordUrl($token, $user));
    }
}
