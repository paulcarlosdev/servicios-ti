<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Instalación en un cliente nuevo: usuario admin + catálogo (empresa, dominios y servicios).
 * No crea clientes, cotizaciones, contratos ni usuarios de ejemplo, y no usa Faker
 * (funciona con `composer install --no-dev`). Es idempotente.
 *
 *   php artisan db:seed --class=ProduccionSeeder --force
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AdminUserSeeder::class, CatalogoSeeder::class]);
    }
}
