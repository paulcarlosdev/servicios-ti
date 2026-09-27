<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\DominioRegistro;
use App\Models\User;
use App\Support\Plantillas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo del prototipo (docs/prototype/assets/data.js), con fechas relativas a hoy
 * para que siempre haya vencimientos cercanos. Solo se ejecuta si no hay clientes.
 * Empresa, dominios y servicios vienen de CatalogoSeeder; los clientes se generan al azar en cada ejecución.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Cliente::exists()) {
            $this->command?->warn('Ya hay datos: se omite DemoSeeder.');

            return;
        }

        $dias = fn (int $n) => today()->addDays($n);

        // Empresa, dominios y servicios: el mismo catálogo que en producción (CatalogoSeeder)
        $catalogo = new CatalogoSeeder;
        $catalogo->setCommand($this->command)->run();
        [$ext, $s, $v] = [$catalogo->dominios, $catalogo->servicios, $catalogo->varios];

        // Clientes aleatorios: [rubro, tipo_doc, estado, días desde el alta]. Los rubros casan con los servicios contratados abajo.
        $c = collect([
            ['Comercial', 'RUC', 'activo', -420],
            ['Tecnologías', 'RUC', 'activo', -300],
            ['Distribuidora', 'RUC', 'activo', -380],
            ['Taller', 'DNI', 'activo', -200],
            ['Clínica', 'RUC', 'activo', -60],
            ['Inversiones', 'RUC', 'inactivo', -700],
        ])->mapWithKeys(fn ($r, $i) => [$i + 1 => Cliente::create(self::clienteAleatorio($r[0], $r[1]) + [
            'estado' => $r[2], 'created_at' => $dias($r[3]), 'updated_at' => $dias($r[3]),
        ])]);
        // Nombre base de cada cliente para sus dominios (p. ej. "comercialgalvan" → comercialgalvan.com.pe)
        $dom = $c->map(fn (Cliente $cli) => self::slug($cli->razon_social));

        foreach ([[1, $dom[1], 3, -355], [3, $dom[3], 2, -352], [4, $dom[4], 2, -370]] as [$cli, $nombre, $tipo, $d]) {
            DominioRegistro::create(['cliente_id' => $c[$cli]->id, 'nombre' => $nombre, 'dominio_tipo_id' => $ext[$tipo]->id, 'estado' => 'activo', 'registrado_at' => $dias($d)]);
        }

        // Línea: [tipo, ref, nombre, identificador, detalle, cantidad, periodo, precio, moneda, ocultarUsd, inicio]
        $linea = function (array $l) use ($s, $v, $ext, $dias) {
            [$tipo, $ref, $nombre, $ident, $detalle, $cant, $periodo, $precio, $moneda, $oculta, $inicio] = $l;

            return [
                'tipo' => $tipo, 'nombre' => $nombre, 'identificador' => $ident, 'detalle' => $detalle, 'cantidad' => $cant,
                'periodo' => $periodo, 'precio' => $precio, 'moneda' => $moneda, 'ocultar_usd' => $oculta,
                'fecha_inicio' => $inicio === null ? null : $dias($inicio),
                'servicio_id' => in_array($tipo, ['vps', 'hosting']) ? $s[$ref]->id : null,
                'servicio_vario_id' => $tipo === 'vario' ? $v[$ref]->id : null,
                'dominio_tipo_id' => $tipo === 'dominio' ? $ext[$ref]->id : null,
            ];
        };

        $contratos = [
            ['CON-2026-0005', 1, -355, 3.75, 'activo', ['CON-2026-0005-firmado.pdf'], -356, [
                ['dominio', 3, 'Dominio .com.pe', $dom[1].'.com.pe', null, 1, 'anual', 40, 'USD', false, -355],
                ['hosting', 5, 'Hosting Plus', $dom[1].'.com.pe', '5 sitios · 100 GB', 1, 'mensual', 18, 'USD', false, -25],
                ['vario', 2, 'Correo corporativo', '5 buzones', null, 5, 'anual', 30, 'USD', false, -355],
            ], [[-356, 'Contrato creado'], [-340, 'Documento firmado subido']]],
            ['CON-2026-0006', 2, -80, 3.72, 'activo', null, -81, [
                ['vps', 2, 'VPS Pro', 'app.'.$dom[2].'.io', '2 vCPU · 4 GB RAM · 50 GB SSD · Ubuntu 24.04 LTS', 1, 'semestral', 135, 'USD', false, -80],
                ['vario', 1, 'Soporte técnico prioritario', null, null, 1, 'mensual', 150, 'PEN', false, -19],
            ], [[-81, 'Contrato creado']]],
            ['CON-2026-0007', 3, -352, 3.78, 'activo', [$dom[3].'-contrato-firmado.pdf'], -353, [
                ['dominio', 2, 'Dominio .pe', $dom[3].'.pe', null, 1, 'anual', 45, 'USD', true, -352],
                ['hosting', 6, 'Hosting Pro', $dom[3].'.pe', '10 sitios · 200 GB', 1, 'anual', 288, 'USD', true, -352],
                ['vario', 5, 'Instalación y migración', null, null, 1, 'unico', 450, 'PEN', false, -352],
            ], [[-353, 'Contrato creado']]],
            ['CON-2026-0004', 4, -370, 3.80, 'activo', null, -371, [
                ['dominio', 2, 'Dominio .pe', $dom[4].'.pe', null, 1, 'anual', 45, 'USD', false, -370],
                ['hosting', 4, 'Hosting Basic', $dom[4].'.pe', '1 sitio · 50 GB', 1, 'anual', 96, 'USD', false, -370],
            ], [[-371, 'Contrato creado']]],
            ['CON-2026-0008', 5, 0, 3.75, 'borrador', null, -2, [
                ['vps', 3, 'VPS Enterprise', 'his.'.$dom[5].'.pe', '4 vCPU · 8 GB RAM · 100 GB SSD · AlmaLinux 9', 1, 'anual', 384, 'USD', false, 0],
                ['vario', 4, 'Backup gestionado', null, null, 1, 'anual', 96, 'USD', false, 0],
            ], [[-2, 'Borrador creado desde COT-2026-0013']]],
        ];

        $porNumero = [];
        foreach ($contratos as [$numero, $cli, $inicio, $tc, $estado, $evidencia, $creado, $lineas, $historial]) {
            $contrato = Contrato::create([
                'numero' => $numero, 'cliente_id' => $c[$cli]->id, 'fecha_inicio' => $dias($inicio), 'tipo_cambio' => $tc,
                'aplica_igv' => true, 'estado' => $estado, 'condiciones_html' => Plantillas::CONDICIONES,
                'documento_evidencia_nombre' => $evidencia[0] ?? null,
                'created_at' => $dias($creado), 'updated_at' => $dias($creado),
            ]);
            foreach ($lineas as $i => $l) {
                $contrato->lineas()->create($linea($l) + ['orden' => $i]);
            }
            foreach ($historial as [$d, $texto]) {
                $contrato->historial()->create(['texto' => $texto, 'created_at' => $dias($d), 'updated_at' => $dias($d)]);
            }
            $contrato->recalcularTotales();
            $porNumero[$numero] = $contrato;
        }

        $cotizaciones = [
            ['COT-2026-0012', 2, -5, 25, 'pendiente', 3.75, null, [
                ['dominio', 1, 'Dominio .com', $dom[2].'.com', null, 1, 'anual', 15, 'USD', false, null],
                ['vario', 2, 'Correo corporativo', '10 buzones', null, 10, 'anual', 30, 'USD', false, null],
            ]],
            ['COT-2026-0013', 5, -12, 18, 'convertida', 3.75, 'CON-2026-0008', [
                ['vps', 3, 'VPS Enterprise', 'his.'.$dom[5].'.pe', '4 vCPU · 8 GB RAM · 100 GB SSD · AlmaLinux 9', 1, 'anual', 384, 'USD', false, null],
                ['vario', 4, 'Backup gestionado', null, null, 1, 'anual', 96, 'USD', false, null],
            ]],
            ['COT-2026-0011', 4, -40, -10, 'rechazada', 3.77, null, [
                ['vps', 1, 'VPS Starter', 'erp.'.$dom[4].'.pe', '1 vCPU · 1 GB RAM · 20 GB SSD · Debian 12', 1, 'mensual', 15, 'USD', false, null],
            ]],
        ];

        foreach ($cotizaciones as [$numero, $cli, $fecha, $validaHasta, $estado, $tc, $contrato, $lineas]) {
            $cot = Cotizacion::create([
                'numero' => $numero, 'cliente_id' => $c[$cli]->id, 'fecha' => $dias($fecha), 'valida_hasta' => $dias($validaHasta),
                'estado' => $estado, 'tipo_cambio' => $tc, 'aplica_igv' => true, 'condiciones_html' => Plantillas::CONDICIONES,
                'contrato_id' => $contrato ? $porNumero[$contrato]->id : null,
            ]);
            foreach ($lineas as $i => $l) {
                $cot->lineas()->create($linea($l) + ['orden' => $i]);
            }
            $cot->recalcularTotales();
            if ($contrato) {
                $porNumero[$contrato]->update(['cotizacion_id' => $cot->id]);
            }
        }

        foreach ([
            ['María Gerente', 'maria@servicios-ti.pe', Rol::Gerente, 'activo', -1],
            ['Juan Técnico', 'juan@servicios-ti.pe', Rol::Tecnico, 'activo', -3],
            ['Lucía Auditora', 'lucia@servicios-ti.pe', Rol::Auditor, 'inactivo', -45],
        ] as [$nombre, $email, $rol, $estado, $acceso]) {
            User::firstOrCreate(['email' => $email], [
                'name' => $nombre, 'password' => env('ADMIN_PASSWORD', 'password'), 'rol' => $rol,
                'estado' => $estado, 'ultimo_acceso_at' => $dias($acceso), 'email_verified_at' => now(),
            ]);
        }

        $this->command?->info('Datos de ejemplo cargados.');
    }

    /**
     * RUC de 11 dígitos con dígito verificador SUNAT (módulo 11) a propósito incorrecto:
     * pasa la validación del formulario pero nunca coincide con una empresa real.
     */
    public static function rucFicticio(string $prefijo = '20'): string
    {
        $base = $prefijo.fake()->numerify('########');
        $suma = 0;
        foreach (str_split($base) as $i => $d) {
            $suma += (int) $d * [5, 4, 3, 2, 7, 6, 5, 4, 3, 2][$i];
        }
        $valido = (11 - $suma % 11) % 10;

        return $base.(($valido + random_int(1, 9)) % 10);
    }

    /** Datos de identidad aleatorios de un cliente (razón social, documento, contacto, correos, teléfono, dirección). */
    public static function clienteAleatorio(string $rubro, string $tipoDoc = 'RUC'): array
    {
        $f = fake();
        $apellido = $f->lastName();
        $nombre = "{$rubro} {$apellido}";
        $razon = $tipoDoc === 'RUC' ? "{$nombre} ".$f->randomElement(['S.A.C.', 'S.A.', 'E.I.R.L.', 'S.R.L.']) : $nombre;
        $contacto = explode(' ', $f->firstName())[0].' '.$f->lastName();
        // Dominio .test (reservado, RFC 2606): los correos de prueba nunca llegan a nadie real
        $dominio = self::slug($razon).'.test';

        return [
            'razon_social' => $razon,
            'tipo_doc' => $tipoDoc,
            'documento' => $tipoDoc === 'RUC' ? self::rucFicticio() : $f->numerify($f->randomElement(['4', '7']).'#######'),
            'contacto' => $contacto,
            'email' => Str::slug($contacto, '.').'@'.$dominio,
            'email_secundario' => $f->boolean(40) ? 'facturacion@'.$dominio : null,
            'telefono' => $f->boolean(85) ? $f->numerify('+51 9## ### ###') : null,
            'direccion' => $f->randomElement(['Av.', 'Jr.', 'Calle']).' '.$f->lastName().' '.$f->numberBetween(100, 2999).', '
                .$f->randomElement(['Miraflores', 'San Isidro', 'Surco', 'Lince', 'Pueblo Libre', 'San Borja', 'Jesús María', 'Los Olivos', 'Cercado de Lima', 'La Molina']),
        ];
    }

    /** "Comercial Gálvez S.A.C." → "comercialgalvez" */
    public static function slug(string $razonSocial): string
    {
        return Str::slug(preg_replace('/\s+(S\.A\.C\.|S\.A\.|E\.I\.R\.L\.|S\.R\.L\.)$/', '', $razonSocial), '');
    }
}
