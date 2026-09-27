<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Rol;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'estado', 'email_verified_at', 'ultimo_acceso_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Permisos por rol (tarjeta "Permisos por rol" del prototipo): E = editar, V = ver.
     */
    public const PERMISOS = [
        'clientes' => ['admin' => 'E', 'gerente' => 'E', 'tecnico' => 'V', 'auditor' => 'V'],
        'documentos' => ['admin' => 'E', 'gerente' => 'E', 'tecnico' => 'V', 'auditor' => 'V'],
        'catalogo' => ['admin' => 'E', 'gerente' => 'E', 'tecnico' => 'V', 'auditor' => 'V'],
        'montos' => ['admin' => 'E', 'gerente' => 'E', 'auditor' => 'V'],
        'empresa' => ['admin' => 'E', 'auditor' => 'V'],
        'usuarios' => ['admin' => 'E'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
        ];
    }

    /** Solo usuarios activos ingresan al panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->estado === 'activo';
    }

    public function puedeVer(string $modulo): bool
    {
        return isset(self::PERMISOS[$modulo][$this->rol?->value]);
    }

    public function puedeEditar(string $modulo): bool
    {
        return (self::PERMISOS[$modulo][$this->rol?->value] ?? null) === 'E';
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }
}
