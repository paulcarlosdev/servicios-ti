<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Filament\Widgets\PermisosPorRol;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageUsuarios extends ManageRecords
{
    protected static string $resource = UsuarioResource::class;

    protected static ?string $title = 'Usuarios';

    protected ?string $subheading = 'Personas con acceso al panel y lo que puede hacer cada rol.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo usuario')
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading('Nuevo usuario')
                ->modalDescription('Recibirá un correo para ingresar.')
                ->mutateDataUsing(fn (array $data) => $data + ['estado' => 'activo', 'email_verified_at' => now()])
                ->createAnother(false)
                ->successNotificationTitle('Usuario creado')
                ->successNotification(fn ($notification, $record) => $notification->body("{$record->name} · {$record->rol->getLabel()}")),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [PermisosPorRol::class];
    }
}
