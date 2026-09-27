<?php

namespace Database\Seeders;

use App\Models\DominioTipo;
use App\Models\Empresa;
use App\Models\Servicio;
use App\Models\ServicioVario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Catálogo base: datos de la empresa (con cuentas y QR de Yape), extensiones de dominio,
 * servicios (VPS / hosting) y servicios varios. No crea clientes, cotizaciones ni contratos.
 *
 * Es idempotente y no pisa cambios hechos desde el panel: la empresa solo se rellena si
 * sigue con los valores por defecto, y cada elemento del catálogo se crea solo si no existe.
 * Deja los registros en $dominios, $servicios y $varios (claves 1..n) para DemoSeeder.
 */
class CatalogoSeeder extends Seeder
{
    public $dominios;

    public $servicios;

    public $varios;

    public function run(): void
    {
        $this->empresa();

        $this->dominios = collect([
            ['.com', 15, 56, 'activo'], ['.pe', 45, 169, 'activo'], ['.com.pe', 40, 150, 'activo'],
            ['.net', 16, 60, 'activo'], ['.org', 14, 53, 'inactivo'],
        ])->mapWithKeys(fn ($r, $i) => [$i + 1 => DominioTipo::firstOrCreate(
            ['extension' => $r[0]], ['precio_usd' => $r[1], 'precio_pen' => $r[2], 'estado' => $r[3]],
        )]);

        // [tipo, nombre, cpu, ram, sitios, disco, extra, mensual, semestral, anual, destacado]
        $this->servicios = collect([
            ['vps', 'VPS Starter', 1, 1, null, 20, 'IP dedicada · Backup diario', 15, 81, 144, false],
            ['vps', 'VPS Pro', 2, 4, null, 50, 'IP dedicada · Backup diario', 25, 135, 240, true],
            ['vps', 'VPS Enterprise', 4, 8, null, 100, 'IP dedicada · Backup diario · Snapshots', 40, 216, 384, false],
            ['hosting', 'Hosting Basic', null, null, 1, 50, 'cPanel · SSL gratis · MySQL', 10, 54, 96, false],
            ['hosting', 'Hosting Plus', null, null, 5, 100, 'cPanel · SSL gratis · MySQL · Anti-DDoS', 18, 97, 173, true],
            ['hosting', 'Hosting Pro', null, null, 10, 200, 'cPanel · SSL gratis · MySQL · Anti-DDoS', 30, 162, 288, false],
        ])->mapWithKeys(fn ($r, $i) => [$i + 1 => Servicio::firstOrCreate(['nombre' => $r[1]], [
            'tipo' => $r[0], 'cpu' => $r[2], 'ram' => $r[3], 'sitios' => $r[4], 'disco' => $r[5], 'extra' => $r[6],
            'precio_mensual' => $r[7], 'precio_semestral' => $r[8], 'precio_anual' => $r[9], 'moneda' => 'USD', 'destacado' => $r[10], 'estado' => 'activo',
        ])]);

        // [nombre, descripción, mensual, semestral, anual, único, moneda]
        $this->varios = collect([
            ['Soporte técnico prioritario', 'Atención remota y visitas programadas, respuesta en 4 horas.', 150, 810, 1440, null, 'PEN'],
            ['Correo corporativo', 'Buzón de 25 GB con antispam, precio por cuenta.', 3, 16, 30, null, 'USD'],
            ['Certificado SSL Wildcard', 'Cubre el dominio y todos sus subdominios.', null, null, 60, null, 'USD'],
            ['Backup gestionado', 'Copias diarias externas con retención de 30 días.', 10, 54, 96, null, 'USD'],
            ['Instalación y migración', 'Migración de sitio, correo y base de datos desde otro proveedor.', null, null, null, 450, 'PEN'],
        ])->mapWithKeys(fn ($r, $i) => [$i + 1 => ServicioVario::firstOrCreate(['nombre' => $r[0]], [
            'descripcion' => $r[1], 'precio_mensual' => $r[2], 'precio_semestral' => $r[3],
            'precio_anual' => $r[4], 'precio_unico' => $r[5], 'moneda' => $r[6], 'estado' => 'activo',
        ])]);

        $this->command?->info('Catálogo listo: empresa, '.$this->dominios->count().' dominios, '
            .$this->servicios->count().' servicios y '.$this->varios->count().' servicios varios.');
    }

    private function empresa(): void
    {
        $empresa = Empresa::actual();

        // Ya configurada desde "Mi empresa": no se toca
        if ($empresa->razon_social !== 'Mi empresa S.A.C.') {
            return;
        }

        $empresa->update([
            'razon_social' => 'ServiciosTI S.A.C.', 'nombre_comercial' => 'ServiciosTI', 'ruc' => '20181520006',
            'email' => 'contacto@servicios-ti.pe', 'telefonos' => '(01) 555 1234 · +51 987 654 321',
            'direccion' => 'Av. Principal 123, Miraflores, Lima', 'web' => 'servicios-ti.pe',
            'yape_numero' => '987 654 321', 'yape_titular' => 'ServiciosTI S.A.C.',
            'tipo_cambio' => 3.75, 'igv' => 0.18, 'validez_cotizacion' => 30,
        ]);

        foreach ([
            ['banco' => 'BCP', 'moneda' => 'PEN', 'numero' => '191-2345678-0-12', 'cci' => '00219100234567801254'],
            ['banco' => 'BBVA', 'moneda' => 'USD', 'numero' => '0011-0123-0100045678', 'cci' => '01112300010004567812'],
        ] as $cuenta) {
            $empresa->cuentas()->firstOrCreate(['numero' => $cuenta['numero']], $cuenta);
        }

        // QR de Yape incluido en el proyecto (database/seeders/archivos) → disco public
        $qr = database_path('seeders/archivos/yape-qr.png');
        if (! $empresa->yape_qr_path && is_file($qr)) {
            Storage::disk('public')->put('empresa/yape-qr.png', file_get_contents($qr));
            $empresa->update(['yape_qr_path' => 'empresa/yape-qr.png']);
        }
    }
}
