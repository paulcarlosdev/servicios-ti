<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea (o actualiza) el usuario administrador del panel Filament.
 * Credenciales en .env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
 * Es idempotente: se puede ejecutar varias veces sin duplicar el usuario.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@servicios-ti.pe');
        $password = env('ADMIN_PASSWORD');

        if (blank($password)) {
            $this->command?->error('Define ADMIN_PASSWORD en el archivo .env antes de ejecutar este seeder.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => $password, // el cast 'hashed' del modelo lo encripta
                'rol' => 'admin',
                'estado' => 'activo',
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Usuario admin listo: {$email}");
    }
}
